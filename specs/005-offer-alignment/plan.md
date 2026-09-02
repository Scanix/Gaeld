# Implementation Plan: Gäld Offer Alignment

**Branch**: `005-offer-alignment` | **Date**: 2026-09-02 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-offer-alignment/spec.md`

## Summary

Align the hosted SaaS offer with four customer-facing categories while preserving
Community Edition behavior and existing subscriptions. The implementation keeps
the current plan and subscription architecture, adds canonical Cloud Free/Solo/
Team plan data and quota fields, introduces shared server-side quota resolution
for monthly OCR and document storage, gives Solo and Team the same explicit
no-card trial with Cloud Free fallback, and updates the Next.js landing site, Inertia billing UI,
public documentation, contracts, and tests.

The rollout is deliberately incremental. Existing legacy Starter and Business
subscriptions remain valid and retain their agreed price. New public signups see
only Cloud Free, Solo, and Team; the Community Edition remains a separate
self-hosted path.

## Technical Context

**Runtime**: PHP 8.4, Laravel 13, PostgreSQL 16, Redis 7, Inertia.js v3, Vue 3, Next.js 16, TypeScript 6, Stripe SDK, pnpm.

**Architecture**: Core owns accounting, uploads, invoices, expenses, organization membership, feature flags, and CE API behavior. The EE plugin owns SaaS plans, subscriptions, Stripe lifecycle, SaaS-only entitlements, signup, and billing. Cross-edition quota behavior uses a small core contract with an EE implementation and a CE-safe default rather than importing EE classes into core.

**Storage**: Existing `ee_plans` and `ee_subscriptions` tables; PostgreSQL migration for monthly OCR/storage policy fields and per-organization document-storage usage; Redis remains the atomic counter/cache backend for short-lived quota reservations. Existing local file storage remains the document store during this feature.

**Testing**: PHPUnit through Sail for core and EE feature/security tests; Vitest for website units; Playwright for pricing/signup acceptance where available; Pint, PHPStan, `vendor/bin/sail pnpm run build`, `pnpm build` in `web`, and `pnpm build` in `docs` as applicable.

**Target Platform**: Existing mutualized production host during rollout; SaaS staging first; Community Edition must build and run without the EE plugin.

**Performance Goals**: Quota checks add bounded work to invoice, OCR, upload, invitation, and API requests; storage usage checks must not recursively scan an organization directory on every request. New public pricing pages must preserve current page performance and SEO metadata.

**Constraints**: No silent price increase; no duplicate Stripe subscription; no data loss during migration or downgrade; no CE-to-EE source coupling; no client-only quota enforcement; no public promise for an unverified backup/support/retention claim.

**Scale/Scope**: Existing early SaaS population, one organization per SaaS subscription, four locales, three hosted plan records plus immutable legacy paid records, four primary quota families, and all affected web/API/documentation surfaces.

## Constitution Check

- [x] Core accounting writes and existing organization isolation remain unchanged.
- [x] SaaS-only behavior stays inside the EE plugin or uses existing core extension contracts.
- [x] The change is reviewable and phased; legacy subscriptions are preserved instead of rewritten destructively.
- [x] Server-side authorization and quota enforcement remain authoritative.
- [x] All customer-visible behavior receives focused automated coverage.
- [x] No dependency change is required.
- [x] Existing CE builds and workflows remain valid with SaaS disabled and EE unavailable.
- [x] Public claims are gated by the offer readiness evidence rather than assumed from configuration.

## Design Decisions

### 1. Canonical and legacy plans

Use stable database identities and separate public availability from historical
compatibility:

- Keep the existing `free` plan record identity and convert its public name and
  limits to **Cloud Free**.
- Add canonical `solo` and `team` plan records at CHF 15 and CHF 39.
- Mark existing `starter` and `business` plan records unavailable for new
  signups and new public selection, but do not delete them or invalidate active
  subscriptions. They remain available to historical subscriptions, billing
  synchronization, admin diagnostics, and history views.
- Keep legacy Stripe price IDs and local billing invoices unchanged for existing
  paid customers.
- Create and configure new Stripe prices for `solo` and `team` only after the
  application plan records and checkout tests are ready.
- Do not rename a legacy database slug in place when that could change the
  meaning of an existing Stripe metadata reference. Public labels and canonical
  plan records are the compatibility boundary.

### 2. Paid-plan trials and Cloud Free fallback

Cloud Free signup creates an active Cloud Free subscription and goes directly to
onboarding. Solo and Team signup create a local paid-plan trial and go to
onboarding without collecting a payment method. The owner explicitly starts a
paid Solo or Team Checkout from Billing when they choose to convert.

A trial-expiry action or reconciliation path must atomically replace an expired
Solo or Team trial with the existing Cloud Free plan assignment. The fallback
must be idempotent, preserve organization data, and be safe if a paid conversion
races with expiry. No automatic paid Stripe subscription or charge is created by
the trial path.

The current `payment_method_collection: always` path is not reused for the
no-card paid-plan trial. Paid Checkout remains responsible for collecting payment
details only after an explicit paid-plan choice.

### 3. Quota contract

Add a small core `OrganizationQuotaResolver` contract with a CE-safe default and
an EE subscription-backed implementation. Core controllers and services use the
contract for invoice, OCR, upload, and member checks; they do not access an EE
Plan class directly.

The plan contract adds:

- `max_ocr_scans_per_month`, where `-1` means unlimited;
- `max_storage_bytes`, where `-1` means unlimited.

The existing daily OCR field remains for legacy paid plans and Solo/Team daily
policy where applicable. Cloud Free uses the new monthly OCR limit of 5 and no
live bank synchronization. Invoice limits continue to use the existing monthly
quota concept.

Document storage usage covers customer-uploaded receipt and invoice
justificatif files. A per-organization usage record is updated only after a
successful file store and decremented after a successful delete. Upload checks
reserve or lock the usage row before persistence so concurrent uploads cannot
cross 250 MB. A repair/backfill command calculates usage for existing records
before the quota is enabled for affected tenants.

### 4. Feature entitlements

Use the existing SaaS `SubscriptionFeatureResolver` plan-gated feature list.
Canonical plan features are:

- Cloud Free: core accounting, `bank_import`; no live sync, API, automation,
  multi-currency, advanced permissions, payroll, or other paid-only flags.
- Solo: core accounting, `bank_import`, and the explicitly verified Solo hosted
  conveniences; no API or advanced automation at launch.
- Team: Solo plus live synchronization, automatic reconciliation, automation,
  multi-currency, API, rule engine, advanced permissions, payroll, and
  withholding tax only where those capabilities pass their existing operational
  and compliance gates.

CE behavior remains controlled by CE configuration when SaaS mode is disabled.
In SaaS mode, the existing plan-gated feature list prevents global feature flags
from bypassing Cloud Free/Solo restrictions.

### 5. Public contract and naming

The website is the public acquisition surface and uses static localized plan
copy. The application is server-authoritative for the actual available plans and
quotas. Both must publish the same names and numbers:

- Community Edition: CHF 0 forever, self-hosted and complete;
- Cloud Free: CHF 0 permanently, 1 organization, 1 user, 5 invoices/month, 5
  OCR scans/month, 250 MB documents, CAMT import, core accounting, and export;
- Solo: CHF 15/month, up to 3 users;
- Team: CHF 39/month, up to 5 users.

The Solo and Team trials and Cloud Free fallback are explained in signup,
Billing, the SaaS getting-started guide, and the pricing FAQ/copy. Old Starter/Business names
may appear only as clearly marked legacy records for existing customers or
migration documentation.

## Affected Surfaces

### Core API and services

- `app/Support/Contracts/OrganizationQuotaResolver.php` and CE default binding
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Domains/Invoicing/Controllers/InvoiceController.php`
- `app/Domains/Expenses/Controllers/ExpenseReceiptController.php`
- `app/Domains/Organizations/Services/InvitationService.php`
- `app/Support/Services/FileUploadService.php` or the owning upload boundary
- organization document-storage usage model/service/migration
- CE API feature enforcement and shared application contract

### EE billing and SaaS

- `plugins/gaeld-ee/migrations/`
- `plugins/gaeld-ee/src/Domains/Billing/Models/Plan.php`
- `plugins/gaeld-ee/src/Domains/Billing/Controllers/RegistrationController.php`
- `plugins/gaeld-ee/src/Domains/Billing/Controllers/BillingController.php`
- `plugins/gaeld-ee/src/Domains/Billing/Services/BillingService.php`
- `plugins/gaeld-ee/src/Domains/Billing/Jobs/` or reconciliation action for trial expiry
- `plugins/gaeld-ee/src/Support/SubscriptionFeatureResolver.php`
- EE provider bindings and scheduled registration

### Website

- `../web/src/app/[locale]/pricing/page.tsx`
- `../web/src/app/(en)/pricing/page.tsx`
- `../web/messages/en.json`
- `../web/messages/fr.json`
- `../web/messages/de.json`
- `../web/messages/it.json`
- website pricing and SEO tests

### Documentation and contracts

- `../docs/docs/billing.md`
- `../docs/docs/getting-started-saas.md`
- localized documentation files if maintained separately
- `api/contract/app-contract.json`
- `../web/contract/app-contract.json` only if the shared frontend contract changes
- `../wiki/api/OFFER_DEFINITION.md`
- `../wiki/api/OFFER_ENTITLEMENTS_MATRIX.md`
- `../wiki/api/OFFER_READINESS_CHECKLIST.md`

## Data Model and Compatibility

### Plan records

| Public offer | Internal plan strategy | Price | Users | Invoice quota | OCR quota | Storage quota |
|---|---|---:|---:|---:|---:|---:|
| Community CE | No SaaS plan record | CHF 0 forever | Installation policy | CE policy | CE policy | Customer infrastructure |
| Cloud Free | Existing `free` identity, public name updated | CHF 0 | 1 | 5/month | 5/month | 250 MB |
| Solo | New canonical `solo` record | CHF 15/month | 3 | Unlimited | Published Solo policy | Published Solo limit |
| Team | New canonical `team` record | CHF 39/month | 5 | Unlimited | Unlimited/fair use if approved | Published Team limit |
| Legacy Starter | Existing `starter` identity, hidden from new selection | Existing price | Existing entitlement | Existing entitlement | Existing entitlement | Existing policy |
| Legacy Business | Existing `business` identity, hidden from new selection | Existing price | Existing entitlement | Existing entitlement | Existing entitlement | Existing policy |

The final schema should use explicit public/legacy availability rather than
inferring public status from price or feature count. If the existing
`is_active` field is used for this transition, the implementation must ensure
that active subscriptions referencing a legacy inactive plan continue to resolve
entitlements and billing history.

### State transitions

```text
Cloud Free signup -> Cloud Free active
Solo signup -> Solo trialing -> Solo active (explicit paid choice)
Team signup -> Team trialing -> Team active (explicit paid choice)
Solo/Team trialing -> Cloud Free active (no paid choice at expiry)
Legacy Starter/Business -> same legacy subscription (migration)
Legacy paid -> Solo/Team active (explicit plan change)
```

Downgrades must preserve existing data. If current usage exceeds the lower
plan's limit, the customer can read and export existing records but cannot create
new usage beyond the limit until they delete data, reduce members, or upgrade.
The exact behavior must be visible before the change is confirmed.

## API and UI Contract

The server exposes authoritative plan data to registration and Billing views,
including public display name, stable internal slug, price, user limit, invoice
limit, OCR period/limit, storage limit, feature list, trial eligibility, and
whether checkout is available. Legacy plans are returned only when needed to
render an existing subscription and are marked legacy.

Shared Inertia quota props expose the current usage and period for invoice, OCR,
storage, and member quotas. Missing quota data is represented as unavailable,
not silently as zero. The frontend may display a quota but cannot authorize a
mutation.

The public website keeps structured JSON-LD offers synchronized with visible
cards and localized copy. It must not describe legacy Starter/Business prices as
current offers.

## Phased Implementation

### Phase 0 - Contract freeze and test fixtures

Freeze the approved matrix, define exact Solo/Team inherited entitlements, record
the legacy migration policy, and add fixtures that distinguish canonical plans
from legacy plans. No customer-visible behavior changes in this phase.

### Phase 1 - Plan persistence and compatibility migration

Add plan quota fields and public/legacy metadata, create Cloud Free/Solo/Team
canonical records, preserve legacy records, and implement an idempotent migration
for existing subscriptions. Verify migration rollback/forward behavior and
Stripe identifier preservation before enabling the new signup catalog.

### Phase 2 - Server-side entitlement and quota enforcement

Introduce the quota resolver boundary, monthly OCR usage, document-storage usage
and backfill, then apply checks to invoices, OCR, uploads, member invitations,
feature flags, and API operations. Add clear error payloads and quota props.

### Phase 3 - Signup, paid-plan trials, and billing lifecycle

Change public plan selection, create Cloud Free directly, create Solo and Team
trials without payment collection, implement explicit paid conversion, and add
the idempotent paid-trial-to-Cloud-Free fallback. Preserve legacy paid Checkout
and existing Stripe subscription change behavior.

### Phase 4 - Website, hosted UI, documentation, and localization

Update the Next.js pricing page, JSON-LD, four locale message files, Inertia
signup/Billing labels and quota displays, public billing documentation, SaaS
getting-started instructions, and application contracts. Remove stale active
claims and clearly identify CE versus Cloud Free.

### Phase 5 - Staging rollout and economic observation

Deploy to staging, run the focused suites and browser acceptance, exercise quota
boundaries and migration fixtures, then enable the new catalog behind the
existing SaaS registration controls. Observe Cloud Free cost, storage, OCR,
abuse, conversion, support, and legacy migration metrics before broadening
acquisition.

## Verification Plan

1. Run the plan-record and migration tests with legacy subscriptions and Stripe
   fakes.
2. Run core quota tests for invoices, OCR, storage, invitations, and API gates.
3. Run Solo/Team trial creation, expiry, race, conversion, and no-charge tests.
4. Run four-locale website unit checks and pricing-page E2E checks.
5. Build CE and EE application bundles separately and verify no EE source enters
   CE.
6. Build the documentation site and search for stale `Starter`, `Business`,
   CHF 9, CHF 29, three-user, unlimited-user, and no-card contradictions in
   active public copy.
7. Run focused PHPUnit tests, Pint, PHPStan, website tests/build, and docs build
   through the repository's supported commands.
8. Run the existing SaaS and security regression suites before staging.
9. Perform a staging acceptance run for signup, Billing, quotas, API denial,
   trial fallback, legacy plan display, export, and all four locales.

## Rollout and Rollback

- Keep the feature disabled or registration-gated until Phase 3 tests and
  staging checks pass.
- Introduce canonical plans without deleting legacy rows.
- Preserve existing legacy customers at their current price during a defined
  protection period or until explicit plan change.
- Roll back application code to the prior release if needed; do not roll back a
  completed data migration by deleting plan or subscription rows.
- If a quota defect is found, disable new Cloud Free signups or the affected
  expensive feature prospectively while preserving existing data and exports.
- If Stripe setup is incomplete, keep Solo/Team paid conversion unavailable while
  leaving their cardless trials, Cloud Free, and CE accessible rather than
  accepting a paid conversion that cannot be fulfilled.

## Open Evidence Gates

The following are implementation gates, not unresolved product choices:

- exact Solo and Team OCR/storage limits beyond the user-count requirement;
- Stripe price IDs for Solo and Team;
- no-card Solo/Team trial provider behavior;
- document storage backfill result;
- backup/restore, support, retention, and hosting claims;
- operational budget per Cloud Free organization;
- legal review of tax and retention wording.

Until these gates are evidenced, the affected claim remains qualified in public
copy and unchecked in the readiness checklist.
