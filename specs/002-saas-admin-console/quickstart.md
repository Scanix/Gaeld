# SaaS Admin Console Quickstart

This runbook validates the feature after the implementation tasks are complete.
It is an acceptance guide, not an implementation guide.

## Prerequisites

- Start the application with `vendor/bin/sail up -d`.
- Use an EE-enabled environment with `FEATURE_SAAS=true` and the plugin enabled.
- Configure a verified SaaS admin user with 2FA and `SAAS_ADMIN_EMAIL`.
- Prepare at least one organization for each state: active, trialing,
  past-due, local-only plan, dormant, suspended, and never used.
- Prepare one Stripe-linked subscription, one failed/unprocessed webhook
  receipt, and representative members with different locales.
- Keep a separate CE test environment/configuration with `FEATURE_SAAS=false`
  and EE plugin loading disabled.

## Automated Gates

Run the focused plugin suite, security regressions, static checks, and build:

```bash
vendor/bin/sail artisan test --compact plugins/gaeld-ee/tests/Feature/SaasAdmin
vendor/bin/sail artisan test --compact tests/Security/Auth/AuthBypassTest.php tests/Security/Billing/StripeWebhookSecurityTest.php
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail bin phpstan analyse --memory-limit=2G
vendor/bin/sail pnpm run build
```

Run the CE subset separately with EE disabled:

```bash
FEATURE_SAAS=false PLUGINS_ENABLED=false vendor/bin/sail artisan test --compact \
   tests/Feature/SaasAdmin/CeInertiaCompatibilityTest.php \
  tests/Feature/Organizations/OrganizationCrudFlowTest.php \
  tests/Feature/Organizations/OrganizationSwitchFlowTest.php \
  tests/Feature/Organizations/FeatureFlagTest.php \
  tests/Security/Auth/AuthBypassTest.php
```

## Browser Acceptance Run

Reset the test/staging database with
`vendor/bin/sail artisan gaeld:saas-admin-fixtures --fresh` before each run.
Launch the application with `vendor/bin/sail open`, then use the VS Code Browser
tool with its Chromium engine, with a 1440x900 desktop viewport and a 390x844
mobile viewport. Record one pass/fail result, the viewport, the fixture
identifier, and a screenshot for each step using the filename pattern
 `step-01-desktop-pass.png` (increment the step number and use `mobile` or
 `fail` as applicable); save `run-trace.zip` only when the Browser tool exposes
 trace export
 under `docs/qa/saas-admin-console/YYYYMMDD-HHmmss/`, using the UTC run start
 time as the directory name. Do not substitute a different browser harness for
 release evidence.

1. Sign in as the configured SaaS admin, complete the SaaS admin 2FA
   confirmation, and verify the Overview opens with separate metrics for
   subscriptions, activation, health, MRR, and operations.
2. Open Organizations, search by organization name, UUID, and owner email;
   apply subscription and health filters; change sort; move to another page;
   confirm the URL preserves filters and the empty result state is explicit.
3. Open an organization detail page and verify members, roles, best-known
   access signal, product usage, subscription history, local/Stripe linkage,
   and admin audit entries. Confirm unavailable values are shown as unknown,
   not as zero.
4. Suspend, reactivate, assign a plan, revoke a plan, and attempt a repeated
   or invalid action. Confirm success, rejection, rollback, and reason are
   visible and each attempt appears in the audit with actor, target, action,
   outcome, and timestamp.
5. Open Billing, inspect local-only and Stripe-linked subscriptions, request a
   Stripe diagnostic, then repeat with Stripe unavailable. Confirm the local
   billing view stays usable and no raw Stripe payload or payment secret is
   shown.
6. Open Health and Operations, switch between 7/30/90-day views, inspect
   dormant cohorts and webhook failures, set and clear the system message, and
   disable/re-enable registrations. Confirm each global operation is reversible
   and audited.
7. Start a support session for a selected member with a reason. Confirm the
   persistent banner identifies support mode, the stop action is visible, the
   SaaS admin/security/billing routes are denied, and writes retain the original
   admin identity. Use keyboard only
   to open/cancel a confirmation, confirm focus returns to the triggering
   control, and stop support access.
8. Prepare an email with explicit recipients and verify the preview, locale,
   duplicate prevention, queued state, partial failure state, and campaign
   audit. Confirm no implicit all-member delivery occurs.
9. Request operational CSV, financial, and customer-data exports with filters
   and a period. Confirm queued/completed/failed/expired states, multi-tenant
   scope, signed URL expiry, cleanup, and separate audit records. Verify a
   customer-data export requires its stronger confirmation and reason.
10. Repeat the main search and Overview flows on mobile. Confirm filters,
    tables, dialogs, banners, focus states, and long organization names do not
    overlap or hide required actions.

Run the ten numbered steps as ten scripted support tasks. Count a task as
successful only when its stated result is observed and its evidence is saved;
the usability gate passes at 9/10 successful tasks. Verify the 15-minute
support expiry and clock-dependent campaign/export expiry in the backend
feature tests with `Carbon::setTestNow()` in `SupportSessionTest.php`,
`CampaignTest.php`, and `ExportTest.php`;
 the browser run must not wait in real time.

### SC-002 Discovery Sample

Run these ten organization-discovery tasks as a separate scripted sample using
the deterministic fixture identifiers below. Each task must use only the SaaS
admin console, open the expected organization detail, and save evidence using
the step filename convention above. The gate passes at 9/10 successful tasks.

1. Search `Org-0042` by name and open `Org-0042`.
2. Search the full UUID of `Org-0100` and open `Org-0100`.
3. Search the owner email of `Org-0125` and open `Org-0125`.
4. Filter subscription state `past_due` and open `Org-0300`.
5. Filter health state `dormant` and open `Org-0400`.
6. Filter health state `never_used` and open `Org-0450`.
7. Sort by last activity ascending and open `Org-0450`, the deterministic
   first row with no known activity.
8. Keep the default name sort and page size 25, move to page 2, and open
   `Org-0026`, the deterministic page-2 organization defined by the fixture.
9. Combine name search `Org-04`, active subscription, and healthy health
   filters, then open `Org-0420`.
10. Search an absent identifier, observe the explicit empty state, clear the
   filter, and open `Org-0001`.

## Performance Acceptance

Use the deterministic `SaasAdminAcceptanceDataset` with 5,000 organizations,
5,000 owners, 5,000 subscriptions distributed across active/trialing/
past-due/canceled states, and at least one usage row per organization. Under
Sail with PostgreSQL, array cache, synchronous queue, and no external network,
run one warm-up request per endpoint followed by twenty timed Overview and
filtered-search requests per endpoint. Instrument SQL through `DB::listen` and request memory through
`memory_get_peak_usage(true)`.

Pass criteria:

- p95 latency is below 3 seconds for both flows;
- each request performs no more than 30 database queries;
- each request adds no more than 128 MB memory;
- no query loads all organizations or subscriptions into an unbounded
  collection;
- no per-organization query loop is present.

## Evidence and Failure Handling

Store screenshots/traces and command output with the feature review. A failed
queued job, expired URL, rejected mutation, or unavailable Stripe request is a
valid tested state only when it is explicit in the interface and has a durable
admin audit outcome. Do not mark the feature ready while the architecture
checklist in `checklists/architecture.md` contains an unresolved security,
ownership, tenant-isolation, or rollback concern.
