# Implementation Plan: SaaS Admin Console

**Branch**: `002-saas-admin-console` | **Date**: 2026-08-20 | **Spec**: [spec.md](spec.md)

## Summary

Replace the current monolithic EE SaaS admin dashboard with a task-oriented
operator console. The design keeps SaaS-only behavior inside
`plugins/gaeld-ee`, preserves the current verified-email plus 2FA confirmation
boundary, and introduces a clear application architecture:

- read-only query objects and response DTOs for overview, organizations,
  billing, health, and operations;
- explicit Actions and Form Requests for mutations;
- a central SaaS admin authorization and audit boundary;
- a short-lived support-session boundary that cannot enter privileged admin
  workflows;
- queued communication and export workflows with signed downloads;
- on-demand Stripe diagnostics through the existing BillingService;
- focused Inertia pages instead of one page containing every table.

The implementation deliberately does not add a generic repository layer. Query
objects are introduced only where they remove full-table loading, repeated
aggregate logic, or cross-domain read coupling.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 13

**Primary Dependencies**: Inertia.js v3, Vue 3, Vite, Tailwind CSS v4,
PostgreSQL, Redis, Horizon v5, Stripe PHP SDK, Spatie Activity Log, existing
Lucide and Chart.js frontend dependencies

**Storage**: PostgreSQL for durable admin audit, campaign, and export metadata;
Redis/cache for existing confirmation and kill-switch state; local storage for
queued export artifacts; Stripe for on-demand external billing diagnostics

**Testing**: PHPUnit through `vendor/bin/sail artisan test --compact`, Pint,
PHPStan, Vite build through `vendor/bin/sail pnpm run build`, and focused
Inertia feature assertions. EE tests must use the existing plugin bootstrap
pattern that enables plugin migrations and routes.

**Target Platform**: Existing Laravel web application deployed with Sail,
PostgreSQL, Redis, Horizon, and the existing browser-supported Vue client

**Project Type**: Existing domain-oriented Laravel web application with an EE
plugin boundary and Inertia/Vue frontend

**Performance Goals**: Initial overview and organization search under 3 seconds
for 95% of representative requests at 5,000 organizations; no response may
load every organization and every subscription into an unbounded in-memory
collection; Stripe calls happen only after an explicit diagnostic request;
large exports run asynchronously

**Constraints**: SaaS-only behavior remains in the EE plugin; CE behavior must
remain unchanged when SaaS is disabled; all admin mutations require the
existing identity and 2FA confirmation; tenant data isolation is mandatory;
all sensitive actions are audited; no payment secrets or full payment details
are exposed; existing named routes remain compatible where practical; no new
dependency or generic repository layer

**Scale/Scope**: 5,000 organizations, potentially many more members and
business records; five admin areas, one organization detail workflow, three
export families, targeted email, support sessions, Stripe diagnostics, and
operations/audit views

## Constitution Check

*GATE: Must pass before design and implementation.*

- [x] **Domain Integrity First**: SaaS Admin owns EE operator workflows;
      organization, user, billing, and accounting domains remain owners of
      their data. The console reads aggregate data and calls existing domain
      actions for mutations. It does not write ledger data.
- [x] **Laravel and Repository Conventions**: The plan reuses Laravel
      controllers, Form Requests, Actions, Jobs, Inertia, Vue components,
      translations, Stripe integration, queue infrastructure, signed URLs, and
      Spatie Activity Log. Query objects are narrow and feature-owned, not a
      new repository abstraction.
- [x] **Behavior Must Be Testable**: Every user story has focused PHPUnit and
      security coverage. Build, Pint, PHPStan, and Sail validation are explicit.
- [x] **Security and Authorization**: Access, support sessions, organization
      targets, Stripe diagnostics, exports, email, and global operations each
      have server-side authorization and failure requirements.
- [x] **Small, Reviewable, Reversible Change**: Work is split into independent
      user stories. New durable records are limited to data needed for audit,
      campaign status, and export lifecycle. Existing SaaS routes and CE paths
      are preserved or explicitly tested.

The architecture checklist in `checklists/architecture.md` is a reviewer-owned
implementation and code-review gate, not an unresolved prerequisite for this
design artifact. It remains unchecked until the implementation demonstrates the
decisions below; the constitution check above records that the design itself
does not violate those decisions.

## Architecture Decision

### Ownership and layers

```text
plugins/gaeld-ee/src/Domains/SaasAdmin/
├── Controllers/       # HTTP orchestration only
├── Requests/          # validation and authorization at request boundary
├── Queries/           # read-only, paginated, aggregate queries
├── DTOs/              # explicit response and filter shapes
├── Actions/           # one mutation/invariant per class
├── Services/          # orchestration across Billing, Users, Organizations
├── Jobs/              # emails and large exports
├── Mail/              # operator-approved targeted messages
├── Models/            # admin-owned durable audit/campaign/export records
├── Policies/          # target and operation authorization
├── Middleware/        # support session boundary and admin context
└── Support/           # status mapping, filter definitions, safe redaction
```

- **Controllers** receive Form Requests, call one query or Action, and return
  an Inertia response, redirect, streamed CSV, or queued response. They do not
  calculate metrics or perform multi-model mutations.
- **Queries** are read-only and return paginated results or immutable response
  DTOs. They use selected columns, conditional aggregates, and explicit
  organization joins. They may use query builder for cross-domain aggregates
  but never accept unvalidated user SQL.
- **Actions** own mutations such as suspend, reactivate, assign/revoke plan,
  delete, set global message, toggle registrations, start/stop support,
  request email, and request export. Mutations that touch multiple records run
  in transactions and publish a success audit event after commit. Rejected
  validation, authorization, state-conflict, provider-failure, and rollback
  outcomes use a separate failure audit path that writes the attempted action
  with its outcome and correlation ID even when no transaction commits.
- **Services** coordinate existing domain services, especially BillingService,
  Organization/User relationships, notification/mail infrastructure, and
  signed export delivery. They do not become catch-all controllers.
- **Models** are only for durable admin-owned metadata. Organization,
  Subscription, Plan, User, DeviceSession, and StripeWebhookEvent remain owned
  by their existing domains.
- **Middleware** resolves the original admin identity and any active support
  session. It must run before ordinary organization context can accidentally
  authorize a privileged admin route. Shared core Inertia middleware may expose
  support props only through a feature/plugin-availability guard; it must not
  resolve EE classes or query EE tables when the plugin is disabled or SaaS is
  off.
- **Frontend pages** consume explicit props and route through named URLs. A
  missing financial prop is an error/unavailable state, never a zero fallback.

### Read/write separation

The Overview, Organizations, Billing, Health, and Operations pages use separate
queries and props. The existing `/saas-admin` route remains the Overview entry
point. New named routes are added for each page and action; URLs are generated
with `route()` or server-provided route data rather than duplicated path strings
in Vue.

The first page response includes only summary data and the first page of the
requested list. Detail-only data and Stripe diagnostics are loaded by explicit
visits. A diagnostic failure does not invalidate local subscription data; it
produces a separate external status.

### Canonical terminology

- **System message** means the global in-app banner shown to users. **Signup
  kill-switch** means the reversible global registration gate. “Operations” is
  the admin area containing both states.
- **Support session** is the canonical term for temporary, visible access to a
  selected member view. “Impersonation” may be used in implementation notes but
  is not a separate product concept.
- **SaaS admin audit** means the durable audit record. **Activity** means
  product/member activity signals and is not a substitute for an admin audit.
- **Stripe diagnostic** means an explicit external lookup. **Local billing
  state** means the persisted plan/subscription state used for fast lists.

### Authorization model

The existing controller access middleware remains the first gate:
configured SaaS admin email, verified user, 2FA enabled, and a recent 2FA
confirmation. A dedicated `SaasAdminContext` and middleware will make the
original admin identity explicit for audit and support access.

Support access is not a second admin identity. It is a short-lived session
record containing original admin ID, target user ID, target organization ID,
reason, started/expiry timestamps, and a nonce. While active:

- a persistent Inertia shared prop drives the support banner and stop action;
- SaaS admin, security/2FA, billing administration, and nested support routes
  are denied;
- ordinary product requests run under the target organization context;
- every write is attributed to the original SaaS admin in the admin audit log;
- expiration and stop invalidate the support session before redirecting.

The first implementation uses the existing web session and does not create a
second authentication guard. It must explicitly prevent privilege confusion
between the original admin and the target member.

## Source Code Structure

### Documentation

```text
specs/002-saas-admin-console/
├── spec.md
├── plan.md
├── tasks.md
├── quickstart.md
└── checklists/
    ├── requirements.md
    └── architecture.md
```

### EE backend

```text
plugins/gaeld-ee/src/Domains/SaasAdmin/
├── Actions/
│   ├── AssignPlanAction.php
│   ├── UpdatePlanAction.php
│   ├── RevokePlanAction.php
│   ├── SuspendOrganizationAction.php
│   ├── ReactivateOrganizationAction.php
│   ├── DeleteOrganizationAction.php
│   ├── SetSystemMessageAction.php
│   ├── ToggleSignupsAction.php
│   ├── StartSupportSessionAction.php
│   ├── StopSupportSessionAction.php
│   ├── CreateCommunicationCampaignAction.php
│   ├── SendCommunicationCampaignAction.php
│   └── CreateExportRequestAction.php
├── Commands/
│   └── SeedSaasAdminAcceptanceDataCommand.php
├── Controllers/
│   ├── SaasAdminController.php
│   ├── OrganizationAdminController.php
│   ├── BillingAdminController.php
│   ├── HealthAdminController.php
│   ├── OperationsAdminController.php
│   ├── SupportSessionController.php
│   └── ExportAdminController.php
├── DTOs/
│   ├── AdminFiltersData.php
│   ├── AdminOperationData.php
│   ├── OverviewMetricsData.php
│   ├── OrganizationListRowData.php
│   ├── OrganizationDetailData.php
│   ├── BillingOverviewData.php
│   ├── HealthOverviewData.php
│   ├── OperationsOverviewData.php
│   ├── StripeDiagnosticData.php
│   └── SupportSessionData.php
├── Jobs/
│   ├── SendSaasAdminCampaignJob.php
│   ├── GenerateOperationalExportJob.php
│   ├── GenerateFinancialExportJob.php
│   ├── GenerateCustomerDataExportJob.php
│   └── ExpireSaasAdminOperationsJob.php
├── Mail/
│   └── SaasAdminCampaignMail.php
├── Middleware/
│   └── ResolveSupportSession.php
├── Models/
│   ├── SaasAdminAudit.php
│   ├── SaasAdminCampaign.php
│   ├── SaasAdminCampaignRecipient.php
│   └── SaasAdminExport.php
├── Policies/
│   └── SaasAdminPolicy.php
├── Queries/
│   ├── OverviewMetricsQuery.php
│   ├── OrganizationListQuery.php
│   ├── OrganizationDetailQuery.php
│   ├── BillingOverviewQuery.php
│   ├── HealthOverviewQuery.php
│   ├── OperationsOverviewQuery.php
│   └── SaasAdminAuditQuery.php
├── Requests/
│   ├── OrganizationFiltersRequest.php
│   ├── BillingFiltersRequest.php
│   ├── HealthFiltersRequest.php
│   ├── AssignPlanRequest.php
│   ├── UpdatePlanRequest.php
│   ├── SuspendOrganizationRequest.php
│   ├── RevokeSubscriptionRequest.php
│   ├── DeleteOrganizationRequest.php
│   ├── StartSupportSessionRequest.php
│   ├── CreateCampaignRequest.php
│   ├── SendCampaignRequest.php
│   └── CreateExportRequest.php
├── Services/
│   ├── SaasAdminAuditService.php
│   ├── SaasAdminContext.php
│   ├── SaasAdminExpiryService.php
│   ├── StripeDiagnosticService.php
│   ├── SupportSessionService.php
│   ├── SaasAdminExportService.php
│   └── SaasAdminMetricsService.php
└── Support/
    ├── AdminStatus.php
  ├── AdminOperationRegistry.php
    ├── RedactsAdminData.php
    └── AdminFilterDefinition.php
```

### EE routes and migrations

```text
plugins/gaeld-ee/routes/web.php
plugins/gaeld-ee/migrations/
├── 2026_08_20_000003_create_ee_saas_admin_audits_table.php
├── 2026_08_20_000004_create_ee_saas_admin_campaigns_tables.php
└── 2026_08_20_000005_create_ee_saas_admin_exports_table.php
```

The migration names are generated at implementation time. Foreign keys and
indexes must match existing UUID organization/user/subscription conventions.
Support-session state is intentionally session-based and does not require a
persisted session table in the first implementation.

### Frontend

```text
plugins/gaeld-ee/resources/js/Pages/SaasAdmin/
├── Dashboard.vue       # Overview entry point, retained route compatibility
├── Organizations.vue
├── OrganizationShow.vue
├── Billing.vue
├── Health.vue
├── Operations.vue
└── Confirm.vue       # existing 2FA confirmation page, explicitly reused

plugins/gaeld-ee/resources/js/Components/SaasAdmin/
├── AdminNavigation.vue
├── AdminMetric.vue
├── AdminFilters.vue
├── OrganizationTable.vue
├── SubscriptionStatus.vue
├── ConfirmAdminAction.vue
└── ExportStatus.vue
```

The SaaS Admin frontend is EE-owned and is implemented under
`plugins/gaeld-ee/resources/js/Pages/SaasAdmin/` and
`plugins/gaeld-ee/resources/js/Components/SaasAdmin/`. The root Vite plugin
registry discovers plugin manifests with a `frontend.pages` declaration and
maps those pages into the Inertia resolver. Plugin-owned imports use the
`@plugins/{slug}` alias; shared core components continue to use `@`.

The registry honors `VITE_PLUGINS_ENABLED` and manifest `feature_gate` values,
so a CE build can omit plugin page chunks entirely while an EE build resolves
`SaasAdmin/*` normally. The core `AppLayout` and `Topbar` do not import SaaS
Admin pages or components. Generic shell components receive plugin-provided
props when a plugin workflow needs to appear in the shell.

Shared `AppLayout`, `Topbar`, `Banner`, `Card`, `Badge`, `Button`, dialog,
translation, formatter, and chart components are reused before creating a new
component. No new frontend dependency is planned.

## Data Model and Persistence

### `ee_saas_admin_audits`

Durable, append-only operator audit records:

- `id` UUID primary key
- `actor_user_id` nullable UUID for original admin
- `target_user_id` nullable UUID
- `organization_id` nullable UUID
- `action` string
- `outcome` bounded string: `succeeded`, `rejected`, `rolled_back`,
  `provider_failed`, `expired`, or `failed`
- `reason` nullable text with bounded length
- `request_id` nullable string/indexed
- `before` nullable JSON, redacted and minimal
- `after` nullable JSON, redacted and minimal
- `metadata` nullable JSON for safe diagnostics
- `transition_key` unique string for idempotent audit transitions
- `fencing_generation` integer captured from the operation owner when the audit
  transition is written
- timestamps

`AdminOperationData` is an immutable descriptor with the following shape:

```text
operation: string                  # registered SaaS admin operation key
route_name: string|null            # named route, if routing reached it
organization_id: UUID|null         # resolved only from a valid route/model target
target_user_id: UUID|null          # resolved only from a valid route/model target
reason_source: string|null         # validated form field, fixed route reason, or null
request_id: string                 # generated before controller/action execution
metadata: string-keyed scalar map  # allow-listed, redacted context only
```

`AdminOperationRegistry` maps every sensitive mutation, external diagnostic,
support, communication, export, and global-operation route to an operation key
and allow-listed route parameters. The required registry entries are
`admin.confirm`, `admin.confirmation.verify`, `organization.view`,
`organization.suspend`, `organization.reactivate`, `organization.delete`,
`organization.grant_plan`, `organization.revoke_plan`, `plan.update`,
`system_message.set`, `system_message.clear`, `signups.disable`,
`signups.enable`, `billing.diagnostic`, `support.start`, `support.stop`,
`campaign.create`, `campaign.send`, `export.create`, and `export.download`.
Read-only Overview, Organizations, Billing, Health, and Operations page routes
use the base SaaS admin authorization gate and query audit, but are not mutation
operation descriptors. A malformed or unresolved target remains null;
the failure audit never performs an unscoped lookup to recover it. Allowed
reason sources are `validated_request`, `fixed_system_reason`, and
`none`. Validation and authorization exceptions map to `rejected`, provider
exceptions to `provider_failed`, transaction exceptions after a started
mutation to `rolled_back`, and other handled failures to `failed`.

Indexes: actor and created time, organization and created time, action and
outcome and created time, request ID. No secrets, passwords, TOTP material, full payment
method data, or raw Stripe payloads are stored.

### `ee_saas_admin_campaigns`

Targeted communication lifecycle:

- `id` UUID primary key
- `created_by_user_id` UUID
- optional `organization_id` for single-organization campaigns
- `segment` nullable JSON for an explicit, reproducible selection
- `recipient_count` integer
- `subject` string
- `body` text or rendered-safe content reference
- `status` enum/string: draft, queued, sending, completed, partially_failed, failed, expired
- `expires_at` nullable timestamp; a queued/sending campaign that reaches this
  timestamp without completion becomes expired and cannot deliver further
- `claim_token` nullable string
- `fencing_generation` integer
- `claimed_at`, `lease_expires_at` nullable timestamps
- `started_at`, `completed_at` nullable timestamps
- timestamps

### `ee_saas_admin_campaign_recipients`

Per-recipient delivery is mandatory because duplicate prevention, locale,
partial failure, expiry, and campaign results are recipient-level behaviors:

- `id` UUID primary key
- `campaign_id` UUID
- `user_id` nullable UUID
- `email` validated string snapshot
- `locale` supported-locale snapshot
- `status` string: pending, claimed, sent, failed, expired
- `claim_token` nullable string
- `fencing_generation` integer
- `claimed_at`, `lease_expires_at`, `sent_at` nullable timestamps
- `failure_reason` nullable bounded text
- timestamps

Unique index on campaign and normalized recipient identity prevents duplicate
delivery. Recipient workers use the campaign fencing contract and transition
keys; campaign status is derived from recipient terminal states.

### `ee_saas_admin_exports`

Export lifecycle and retention metadata:

- `id` UUID primary key
- `requested_by_user_id` UUID
- optional `organization_id`
- `type` string: operational, financial, customer_data
- `filters` nullable JSON
- `period` nullable JSON with explicit start/end dates
- `status` string: queued, processing, completed, partially_failed, failed, expired
- `path` nullable string, never exposed before completion
- `failure_reason` nullable text without secrets
- `expires_at` nullable timestamp
- `claim_token` nullable string
- `fencing_generation` integer
- `claimed_at`, `lease_expires_at` nullable timestamps
- timestamps

All queries creating rows must validate filters and targets server-side. Signed
URLs reference the export ID, not an arbitrary client path.

### Existing data used read-only

- Organizations and memberships from core organization models and pivot.
- Users and `DeviceSession` for members and last-known access signal.
- Plans and subscriptions from EE Billing.
- `StripeWebhookEvent` for safe webhook synchronization status.
- Existing activity log for organization/member history; admin actions use the
  dedicated admin audit record and may also write a safe activity entry.

Every operation owner calls `SaasAdminAuditService`: lifecycle Actions record
success after commit and rejected/rolled-back outcomes through the failure path;
`StripeDiagnosticService` records successful and provider-failed audit outcomes,
with unavailable or mismatch represented as safe diagnostic metadata; support
Actions record start, stop, denial, and expiry;
campaign and export models/jobs keep their own `queued`, `processing`,
`completed`, `partially_failed`, `failed`, and `expired` lifecycle statuses while
emitting audit events with the bounded operation outcome. Controllers do not
write audit rows directly. Form Request authorization/validation failures and
policy denials enter the same failure-audit path through the SaaS admin
operation middleware, so rejected attempts are durable even when no Action
starts. The middleware receives an `AdminOperationData` descriptor containing
the route operation, target identifiers, reason source, and correlation ID;
when a request fails before an Action starts, it uses that descriptor plus the
exception outcome to write the rejection audit.

The lifecycle-to-audit mapping is explicit:

| Campaign/export lifecycle status | Audit outcome | Meaning |
|---|---|---|
| `queued`, `processing`, or `sending` | `succeeded` | The requested operation was accepted and is progressing. |
| `completed` | `succeeded` | The operation completed without failed items. |
| `partially_failed` | `failed` or `provider_failed` | The operation completed with failed items; use `provider_failed` when at least one failed item is attributable to an external provider, otherwise `failed`. |
| `failed` | `failed` | The operation failed inside Gäld without a provider exception. |
| provider exception | `provider_failed` | Stripe, mail, storage, or another external provider failed. |
| `expired` | `expired` | The operation reached its expiry without a valid completion. |
| rejected before queueing | `rejected` | Validation, authorization, target, or state checks prevented queueing. |
| `draft` | `succeeded` | Draft creation is an audited campaign operation; delivery auditing begins when it is queued or rejected. |
| transaction exception after mutation start | `rolled_back` | The transaction was rolled back and no partial success is reported. |

`SaasAdminExpiryService` atomically transitions expired queued, sending, or
processing records to `expired` using a conditional status update
and a unique transition key. `ExpireSaasAdminOperationsJob` runs once per minute
and every campaign or export worker claims work with a token, fencing generation,
and lease using a conditional update before delivery or artifact publication.
Heartbeats, completion, failure, publication, and audit writes all require the
same claim token, fencing generation, and unexpired lease. A claim or reclaim
atomically increments `fencing_generation`; a stale worker whose lease was
reclaimed becomes a no-op and cannot publish, complete, fail, or audit the
operation. The sweeper cannot expire a record with a live lease; an expired
lease can be reclaimed once. Mail recipients and export publication use the same
entity/transition key as an idempotency key, so concurrent workers produce at
most one application-owned delivery/publication and one audit event per
transition. Draft campaigns have no expiry until they are queued.

Worker transition predicates are fixed:

| Transition | Required atomic predicate | Result when predicate fails |
|---|---|---|
| claim | status is queueable and (`lease_expires_at` is null or in the past) | no-op; another worker owns the work |
| reclaim | status is active and `lease_expires_at` is in the past | no-op; current lease remains authoritative |
| heartbeat | matching claim token, matching fencing generation, lease still valid | no-op; worker must stop |
| complete/fail | matching token and generation, lease still valid | no-op; stale worker cannot change state |
| publish/send | matching token and generation, lease still valid, transition key unused | no-op; no second side effect |
| expire | status is non-terminal, `expires_at` is past, and no valid lease exists | no-op; live work is protected |
| audit transition | matching transition key and current token/generation, or an explicitly rejected pre-action descriptor | idempotent no-op |

## Route and Inertia Contract

Keep these existing route names compatible:

- `saas-admin.confirm`
- `saas-admin.verify-confirmation`
- `saas-admin.index`
- `saas-admin.show`
- existing plan, moderation, message, and signup action routes

Add named routes grouped by concern, with resource-style naming where it does
not break existing URLs:

- `saas-admin.organizations.index`
- `saas-admin.organizations.show`
- `saas-admin.billing.index`
- `saas-admin.billing.diagnostic`
- `saas-admin.update-plan` (existing route, owned by `BillingAdminController`)
- `saas-admin.health.index`
- `saas-admin.operations.index`
- `saas-admin.support.start`
- `saas-admin.support.stop`
- `saas-admin.exports.store`
- `saas-admin.exports.download`
- `saas-admin.campaigns.store`
- `saas-admin.campaigns.send`

Each page receives explicit props for `filters`, `pagination`, `metrics`,
`loading/deferred` state where used, and `errors`/unavailable state. Shared
props add `auth.is_support_session` and a redacted `auth.support_session` only
when active. There is no frontend zero fallback for absent financial metrics.

Plan configuration mutations have one owner: `BillingAdminController` accepts
`UpdatePlanRequest` on the existing `saas-admin.update-plan` route and delegates
to `UpdatePlanAction`. `OperationsAdminController` owns only system message,
signup gate, Horizon, and campaign operations; it never delegates plan writes.

## Research and Design Decisions

### Decision: local-first lists, Stripe-on-demand diagnostics

Local plans/subscriptions are indexed and available without an external call,
which keeps lists predictable and allows admin-granted plans. Stripe diagnostics
are a deliberate detail action so a provider outage cannot make the entire
console unusable. The existing BillingService remains the Stripe gateway.

### Decision: query objects instead of a repository layer

The current controller performs full-table loading and repeated aggregate loops.
Narrow query objects solve the actual performance and ownership problem while
remaining close to Laravel's query builder. A generic repository would obscure
cross-table aggregates and add an abstraction with no established local pattern.

### Decision: dedicated audit and export metadata

Spatie Activity Log is useful for model changes, but admin operations, support
sessions, campaigns, and export lifecycle need stable action-specific fields,
retention, correlation IDs, and safe redaction. A dedicated admin audit model
keeps this contract explicit and avoids parsing free-form descriptions.

### Decision: session-based support access first

A second guard or a full impersonation package would broaden the security
surface. A signed, short-lived session context with middleware, an explicit
banner, and original-admin attribution is sufficient for the first workflow.
The implementation must be isolated so a future dedicated support guard can
replace it without changing admin Actions.

### Decision: no live financial PII in list pages

Lists show only aggregate business signals. Member email and detailed customer
data appear only in an authorized organization detail or explicit customer-data
export flow. Stripe responses are reduced to safe summaries and never written
raw to logs or audit rows.

## Test Strategy

### Feature and security tests

Create plugin-enabled tests under `plugins/gaeld-ee/tests/Feature/SaasAdmin/`:

- `SaasAdminAccessTest.php`: guest, non-admin, unverified, no-2FA, expired
  confirmation, and confirmed admin access.
- `OverviewTest.php`: metrics, periods, missing-data states, scale-friendly
  pagination contract, and CE-disabled behavior.
- `OrganizationListTest.php`: search, filters, sorting, pagination, empty
  results, deleted/suspended state, and no cross-organization leakage.
- `OrganizationDetailTest.php`: members, activity source, subscription
  history, Stripe linkage, and admin audit.
- `OrganizationActionsTest.php`: suspend, reactivate, plan assignment,
  revoke, delete, duplicate requests, transactions, and audit.
- `BillingDiagnosticTest.php`: local-only plan, Stripe success, mismatch,
  provider failure, redaction, and no raw payload persistence.
- `HealthOperationsTest.php`: health periods, webhook receipts, message,
  kill-switch, and operation audit.
- `SupportSessionTest.php`: 15-minute expiry, stop, forbidden admin/security/
  billing routes, original-admin attribution, and session isolation.
- `CampaignTest.php`: explicit recipients, locale, queue, duplicate selection,
  invalid email, draft-to-send transition, expiry, partial failure, stale
  recipient workers, and audit.
- `ExportTest.php`: filters, period, operational/financial/customer-data
  separation, queue state, signed download expiry, cleanup, expiry-worker
  races, and scope.
- `tests/Feature/SaasAdmin/CeInertiaCompatibilityTest.php`: boot with SaaS and
  EE disabled, request a shared Inertia page, and assert that no EE class/table
  is resolved.
- `SaasAdminPerformanceTest.php`: deterministic 5,000-organization fixture,
-  one warm-up and twenty timed repetitions per endpoint in isolated PHP
  processes, exact request URLs, SQL query listener, p95 calculation, and
  memory delta assertions.

Use realistic factories and plugin bootstrap; use Stripe fakes/mocks only at the
external gateway boundary. Activity log assertions must follow the repository's
testing environment convention and assert durable state when activity logging
is disabled.

### Frontend and manual validation

- Assert each Inertia component and required prop shape in feature tests;
  retain the existing `SaasAdmin/Confirm.vue` page and add an assertion that
  the new navigation returns to it when confirmation expires.
- Add an automated locale-key parity test for the four supported translation
  files and a named browser validation for keyboard confirmation/cancellation,
  focus behavior, and the persistent support-session banner. The repository
  has no frontend test runner today; adding one is not justified for this
  feature, so the browser validation is an explicit acceptance gate rather than
  an implicit manual afterthought.
- Run the Vite build for every Vue change.
- Use the VS Code Browser tool with its Chromium engine against the
  deterministic dataset from `SaasAdminAcceptanceDataset`; do not substitute a
  different browser harness when recording release evidence. Reset the
  database with `vendor/bin/sail artisan gaeld:saas-admin-fixtures --fresh`
  before each run, use 1440x900 and 390x844 viewports, and save screenshots
  under `docs/qa/saas-admin-console/YYYYMMDD-HHmmss/`. Capture a browser trace
  only when the tool supports trace export.
- Manually verify desktop and mobile navigation, keyboard confirmation flow,
  filters, empty/error states, Stripe diagnostic failure, support banner/stop,
  and expired export URLs.
- Measure query count and memory behavior on a representative 5,000-organization
  fixture; verify no per-organization query loop is introduced.
- Run a named performance test with representative 5,000-organization data:
  use a deterministic seeded dataset of 5,000 organizations, 5,000 owners,
  5,000 subscriptions distributed across active/trialing/past-due/canceled
  states, and at least one usage row per organization; run one warm-up per
  endpoint followed by twenty timed repetitions per endpoint of the exact
  Overview request and exact filtered organization request under Sail with
  PostgreSQL, array cache, synchronous queue, and no external network; collect
  p95 wall time, `DB::listen` query count, and `memory_get_peak_usage(true)`
  delta; fail the gate when p95 exceeds 3 seconds, query count exceeds 30, or
  additional request memory exceeds 128 MB.

## Rollout, Compatibility, and Recovery

- Keep the current `/saas-admin` route as the Overview entry point and redirect
  old links to the appropriate specialized page.
- Introduce tables with nullable fields where possible and deploy migrations
  before enabling writes. Existing plans, subscriptions, activity records,
  webhook receipts, and organization rows remain readable.
- Existing moderation, plan, system-message, and signup routes remain available
  during the transition and delegate to the new Actions.
- New exports use export IDs and expiring signed URLs. Existing signed export
  routes remain readable until their current retention policy expires.
- A failed campaign or export is visible as failed and can be retried through a
  new request; partial artifacts are not published as completed.
- Support-session middleware can be disabled by configuration/route rollback
  without changing organization or user data. Audit records remain for review.
- Add release notes for the new admin workflows and any new environment/config
  values. Do not change CE behavior or require EE migrations when the plugin is
  disabled.

## Validation Runbook

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan test --compact plugins/gaeld-ee/tests/Feature/SaasAdmin
vendor/bin/sail artisan test --compact tests/Security/Billing/StripeWebhookSecurityTest.php
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail bin phpstan analyse --memory-limit=2G
vendor/bin/sail pnpm run build
```

For the manual flow, use an EE-enabled environment with at least one admin,
multiple organization states, one Stripe-linked subscription, one local-only
plan, failed webhook receipt, and a large filtered export. Validate all four
locales and a mobile viewport.

## Complexity Tracking

| Addition | Why needed | Simpler alternative rejected because |
|---|---|---|
| Feature-owned query objects and response DTOs | Prevent full-table loading and keep Inertia contracts explicit | Keeping aggregate loops in the controller preserves the current scalability and coupling problems |
| Dedicated admin audit records | Sensitive actions need stable actor/target/reason/correlation fields and redaction | Free-form activity descriptions are difficult to query and insufficient for support/export review |
| Dedicated campaign/export lifecycle records | Queued operations need status, retry, retention, and signed-download ownership | Cache-only state cannot support reliable history, expiry, or partial failure handling |
| Support-session middleware | Impersonation needs privilege boundaries and original-admin attribution | Directly swapping the authenticated user risks security confusion and cannot safely block admin routes |
