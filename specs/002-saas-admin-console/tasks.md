---

description: "Gäld task list for the SaaS admin console"

---

# Tasks: SaaS Admin Console

**Input**: Design documents from `/specs/002-saas-admin-console/`

**Prerequisites**: [spec.md](spec.md), [plan.md](plan.md)

**Scope rule**: Keep SaaS-only behavior in `plugins/gaeld-ee`; reuse existing
organization, user, billing, queue, audit, translation, and UI conventions.
Do not add a generic repository layer or a new frontend dependency.

## Phase 1: Setup and Contracts

- [X] T001 [P] Add the plugin-enabled SaaS admin test bootstrap in `plugins/gaeld-ee/tests/Feature/SaasAdmin/SaasAdminTestCase.php`
- [X] T002 [P] Add shared SaaS admin test factories/helpers, one deterministic 5,000-organization dataset with page size 25 and stable name-then-ID sorting, and a documented browser subset covering `Org-0001` through `Org-0500`; define unique special-state fixtures where `Org-0300` is the only past-due organization, `Org-0400` is the first and only dormant organization, `Org-0450` is the only never-used organization and the first null-activity row, and `Org-0420` is the only active/healthy organization matching the combined `Org-04` filter; add the `gaeld:saas-admin-fixtures --fresh` seed command for organizations, members, plans, subscriptions, webhook receipts, and admin confirmation in `plugins/gaeld-ee/tests/Feature/SaasAdmin/Concerns/CreatesSaasAdminFixtures.php`, `plugins/gaeld-ee/tests/Feature/SaasAdmin/Support/SaasAdminAcceptanceDataset.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Commands/SeedSaasAdminAcceptanceDataCommand.php`
- [X] T003 [P] Add admin route names and Inertia page/component entries, explicitly retaining `resources/js/Pages/SaasAdmin/Confirm.vue` as the existing 2FA confirmation page, to `contract/app-contract.json`
- [X] T004 [P] Add the SaaS admin translation key inventory for `en`, `fr`, `de`, and `it` in `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, and `lang/it/app.php`

## Phase 2: Foundational Architecture and Security

- [X] T005 Create `ee_saas_admin_audits` migration with UUID actor/target/organization references, action, bounded `outcome` (`succeeded`, `rejected`, `rolled_back`, `provider_failed`, `expired`, or `failed`), unique transition key, fencing generation, reason, request ID, redacted before/after JSON, safe metadata, timestamps, and query indexes in `plugins/gaeld-ee/migrations/2026_08_20_000003_create_ee_saas_admin_audits_table.php`
- [X] T006 [P] Create `ee_saas_admin_campaigns` and mandatory `ee_saas_admin_campaign_recipients` lifecycle migrations with campaign status (`draft`, `queued`, `sending`, `completed`, `partially_failed`, `failed`, `expired`), recipient status (`pending`, `claimed`, `sent`, `failed`, `expired`), explicit segment/recipient metadata, expiry, claim token, fencing generation, lease timestamps, timestamps, unique recipient identity, and delivery indexes in `plugins/gaeld-ee/migrations/2026_08_20_000004_create_ee_saas_admin_campaigns_tables.php`
- [X] T007 [P] Create `ee_saas_admin_exports` migration with type, filters, period, lifecycle status (`queued`, `processing`, `completed`, `partially_failed`, `failed`, `expired`), safe failure reason, path, expiry, claim token, fencing generation, lease timestamps, and ownership indexes in `plugins/gaeld-ee/migrations/2026_08_20_000005_create_ee_saas_admin_exports_table.php`
- [X] T008 [P] Add `SaasAdminAudit.php`, `SaasAdminCampaign.php`, `SaasAdminCampaignRecipient.php`, and `SaasAdminExport.php` models with guarded fields, outcome/status casts, relationships, and redacted serialization in `plugins/gaeld-ee/src/Domains/SaasAdmin/Models/`
- [X] T009 Implement `SaasAdminPolicy` and explicit admin operation authorization methods in `plugins/gaeld-ee/src/Domains/SaasAdmin/Policies/SaasAdminPolicy.php`
- [X] T010 Implement `AdminOperationData.php`, `AdminOperationRegistry.php`, request correlation, the bounded audit outcome contract, and redaction helpers in `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/AdminOperationData.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Support/AdminOperationRegistry.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Support/RedactsAdminData.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`, including a failure path for rejected, provider-failed, and rolled-back operations that does not require a committed transaction
- [X] T011 Implement `SaasAdminContext.php`, `ResolveSupportSession` middleware, and support context DTO in `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminContext.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Middleware/ResolveSupportSession.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/SupportSessionData.php`; preserve original-admin identity for every support request
- [X] T012 Register SaaS admin policy, middleware aliases, route middleware, the SaaS admin operation failure-audit middleware, and Inertia support-session shared props without changing CE behavior in `plugins/gaeld-ee/src/GaeldEEServiceProvider.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Middleware/AuditSaasAdminOperation.php`, and `app/Http/Middleware/HandleInertiaRequests.php`; guard every shared EE prop with SaaS/plugin availability and add the no-EE-resolution assertion to `plugins/gaeld-ee/tests/Feature/SaasAdmin/SaasAdminAccessTest.php`
- [X] T013 [P] Add foundational authorization, redaction, original-admin context, registry completeness, malformed/null target, reason-source allow-list, rejected Form Request/policy/route-binding failure-audit assertions, and `Confirm.vue` expiry redirect assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/SaasAdminAccessTest.php` and `plugins/gaeld-ee/tests/Feature/SaasAdmin/SupportSessionTest.php`

## Phase 3: User Story 1 - Overview and organization discovery (Priority: P1) MVP

**Goal**: Give operators a fast, honest overview and a paginated way to find
organizations.

**Independent Test**: With representative organization/subscription/activity
fixtures, the operator sees separated metrics, searches/filter organizations,
and receives explicit empty/error/unknown states without a full-table response.

### Tests

- [X] T014 [P] [US1] Add overview metric, period, freshness, missing-data, and CE-disabled assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/OverviewTest.php`
- [X] T015 [P] [US1] Add organization name/ID/owner search, status/health filters, sorting, pagination, empty state, suspended/deleted state, and tenant-isolation assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/OrganizationListTest.php`

### Implementation

- [X] T016 [P] [US1] Implement `AdminFiltersData.php`, `OverviewMetricsData.php`, `AdminStatus.php`, `AdminFilterDefinition.php`, and `OrganizationFiltersRequest.php` with explicit filter/status validation in `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Support/`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/`
- [X] T017 [P] [US1] Implement aggregate overview queries with period labels, freshness, conditional counts, MRR, activation, and health classification in `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/OverviewMetricsQuery.php` and `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminMetricsService.php`
- [X] T018 [US1] Implement server-side organization search/filter/sort/pagination with selected columns and aggregate subqueries in `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/OrganizationListQuery.php`
- [X] T019 [US1] Replace the full-table read path in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/SaasAdminController.php` with the overview query and compatible `saas-admin.index` response props
- [X] T020 [US1] Add named organization list route and thin controller method in `plugins/gaeld-ee/routes/web.php` and `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/OrganizationAdminController.php`
- [X] T021 [US1] Add `resources/js/Pages/SaasAdmin/Organizations.vue` with server-driven filters, pagination, explicit loading/empty/error states, and named route links
- [X] T022 [US1] Refactor `resources/js/Pages/SaasAdmin/Dashboard.vue` into an overview with separate KPI, activation, health, alert, and navigation sections; remove duplicated full organization/subscription tables
- [X] T023 [P] [US1] Add `AdminNavigation.vue`, `AdminMetric.vue`, `AdminFilters.vue`, `OrganizationTable.vue`, and `SubscriptionStatus.vue` in `resources/js/Components/SaasAdmin/`
- [X] T024 [US1] Add Overview and Organizations Inertia prop assertions for missing, empty, forbidden, and paginated states in `plugins/gaeld-ee/tests/Feature/SaasAdmin/OverviewTest.php` and `plugins/gaeld-ee/tests/Feature/SaasAdmin/OrganizationListTest.php`

**Checkpoint**: Run US1 tests and inspect the query count/memory behavior with a
large fixture before starting detail and mutation work.

## Phase 4: User Story 2 - Organization diagnosis and lifecycle (Priority: P1)

**Goal**: Provide a complete organization detail workflow with safe,
transactional, audited lifecycle actions.

**Independent Test**: An authorized operator can inspect a target organization,
perform one valid lifecycle action, see the resulting state, and find the
operation in the admin audit. Invalid state transitions and non-admin requests
are rejected without partial writes.

### Tests

- [X] T025 [P] [US2] Add detail props for identity, members, roles, activity source, onboarding, usage, subscriptions, and audit in `plugins/gaeld-ee/tests/Feature/SaasAdmin/OrganizationDetailTest.php`
- [X] T026 [P] [US2] Add suspend, reactivate, assign/revoke plan, delete, duplicate request, transaction rollback, and audit assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/OrganizationActionsTest.php`

### Implementation

- [X] T027 Implement organization detail query and explicit response DTOs using `DeviceSession`/auth audit for last-known access in `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/OrganizationDetailQuery.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/OrganizationDetailData.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/OrganizationListRowData.php`
- [X] T028 Add organization detail route/controller and audit query in `plugins/gaeld-ee/routes/web.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/OrganizationAdminController.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/SaasAdminAuditQuery.php`
- [X] T029 [P] [US2] Add `AssignPlanRequest.php`, `SuspendOrganizationRequest.php`, `RevokeSubscriptionRequest.php`, and `DeleteOrganizationRequest.php` with authorization and state-safe validation in `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/`
- [X] T030 [P] [US2] Implement `SuspendOrganizationAction.php`, `ReactivateOrganizationAction.php`, `AssignPlanAction.php`, `RevokePlanAction.php`, and `DeleteOrganizationAction.php` with success/failure audit outcomes in `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/`
- [X] T031 [US2] Route all existing moderation mutations through the new Actions while preserving existing route names; explicitly exclude `saas-admin.update-plan`, which is owned by `BillingAdminController` in T044, in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/SaasAdminController.php` and `plugins/gaeld-ee/routes/web.php`
- [X] T032 [US2] Rebuild `resources/js/Pages/SaasAdmin/OrganizationShow.vue` with identity, activation, usage, members, subscription history, audit, explicit unknown states, and confirmed action dialogs
- [X] T033 [P] [US2] Add `OrganizationMemberTable.vue`, `UsageSummary.vue`, `AuditTimeline.vue`, and `ConfirmAdminAction.vue`, reusing `SubscriptionStatus.vue` from T023, in `resources/js/Components/SaasAdmin/`
- [X] T034 [US2] Add localized lifecycle, audit, unknown-data, and failure messages in `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, and `lang/it/app.php`

**Checkpoint**: Run US2 access/action tests, verify audit redaction, and confirm
CE mode remains unaffected.

## Phase 5: User Story 3 - Billing, health, and operations (Priority: P2)

**Goal**: Separate commercial, adoption, and platform operations into focused
views with local-first data and explicit Stripe diagnostics.

**Independent Test**: Operators can filter billing, compare health periods, see
webhook failures, request a Stripe diagnostic, and control global operations
without leaving the admin console or exposing raw provider data.

### Tests

- [X] T035 [P] [US3] Add billing status/plan/local-only/Stripe-linkage assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/BillingTest.php`
- [X] T036 [P] [US3] Add Stripe success, mismatch, unavailable, provider-failed, redaction, no-raw-payload, and corresponding audit-outcome assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/BillingDiagnosticTest.php`
- [X] T037 [P] [US3] Add UTC health-period, healthy/quiet/dormant/never-used/suspended/unknown threshold-boundary assertions including exactly 30 x 24 hours as quiet/healthy rather than dormant, quota, webhook receipt, global message, kill-switch, and operations audit assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/HealthOperationsTest.php`

### Implementation

- [X] T038 [P] [US3] Implement `BillingOverviewData.php`, `HealthOverviewData.php`, `OperationsOverviewData.php`, `BillingFiltersRequest.php`, and `HealthFiltersRequest.php` with explicit period/filter validation in `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/` and `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/`
- [X] T039 [P] [US3] Implement billing, health, and operations read queries with explicit periods and pagination, applying the documented health precedence and thresholds, in `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/BillingOverviewQuery.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/HealthOverviewQuery.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/OperationsOverviewQuery.php`
- [X] T040 [US3] Implement `StripeDiagnosticData.php` and on-demand Stripe diagnostic redaction, mismatch/unavailable metadata mapping, and succeeded/provider-failed audit outcomes through the existing billing gateway in `plugins/gaeld-ee/src/Domains/SaasAdmin/DTOs/StripeDiagnosticData.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/StripeDiagnosticService.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`, and `plugins/gaeld-ee/src/Domains/Billing/Services/BillingService.php`
- [X] T041 [US3] Add Billing, Health, and Operations routes with thin `BillingAdminController.php`, `HealthAdminController.php`, and `OperationsAdminController.php` methods in `plugins/gaeld-ee/routes/web.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/BillingAdminController.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/HealthAdminController.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/OperationsAdminController.php`
- [X] T042 [US3] Add `resources/js/Pages/SaasAdmin/Billing.vue`, `Health.vue`, and `Operations.vue` with filters, charts/tables, deferred diagnostics, webhook states, and explicit unavailable/error states
- [X] T043 [US3] Move system-message, signup kill-switch, and Horizon links into the Operations workflow while preserving existing mutation routes in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/OperationsAdminController.php`
- [X] T044 [US3] Implement `SetSystemMessageAction.php`, `ToggleSignupsAction.php`, `UpdatePlanAction.php`, and `UpdatePlanRequest.php`; require `OperationsAdminController.php` to delegate system-message and signup-gate routes, and `BillingAdminController.php` to delegate the existing `saas-admin.update-plan` route, while preserving route names and audit outcomes
- [X] T045 [P] [US3] Add `BillingSummary.vue`, `HealthChart.vue`, and `OperationsPanel.vue` in `resources/js/Components/SaasAdmin/`
- [X] T046 [US3] Add billing, Stripe, health, webhook, and operations translations in `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, and `lang/it/app.php`

**Checkpoint**: Run US3 tests with Stripe unavailable and webhook failures before
adding support and export workflows.

## Phase 6: User Story 4 - Support, communication, and exports (Priority: P2)

**Goal**: Add controlled support access, targeted email, and safe operational,
financial, and customer-data exports.

**Independent Test**: An operator can create and stop a 15-minute support
session, send an explicitly addressed email, and request each export class;
expired, unauthorized, failed, and out-of-scope operations are rejected and
audited.

### Tests

- [X] T047 [P] [US4] Add support-session start/stop/expiry/banner/privilege-denial/original-admin attribution and succeeded/rejected/expired audit-outcome assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/SupportSessionTest.php`
- [X] T048 [P] [US4] Add explicit recipient, recipient-level status/result, preview, locale, draft audit creation, deterministic campaign expiry, draft-to-send acceptance, queue lifecycle (`queued`, `sending`, `completed`, `partially_failed`, `failed`, `expired`), claim/lease stale-worker no-op assertions, duplicate handling, rejected requests, and mapped bounded audit-outcome assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/CampaignTest.php`
- [X] T049 [P] [US4] Add operational/financial/customer-data export filters, queue lifecycle states (`queued`, `processing`, `completed`, `partially_failed`, `failed`, `expired`), signed URL expiry, cleanup, multi-organization scope, claim/lease stale-worker no-op assertions, idempotent transition keys, and mapped bounded audit-outcome assertions in `plugins/gaeld-ee/tests/Feature/SaasAdmin/ExportTest.php`

### Implementation

- [X] T050 [P] [US4] Implement `StartSupportSessionRequest.php`, support-session start/stop Actions, service, routes, privilege-denial middleware integration, and succeeded/rejected/expired audit outcomes in `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/StartSupportSessionRequest.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/StartSupportSessionAction.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/StopSupportSessionAction.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SupportSessionService.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/SupportSessionController.php`, and `plugins/gaeld-ee/routes/web.php`
- [X] T051 [US4] Add shared support banner and original-admin stop control in `resources/js/Components/SaasAdmin/SupportSessionBanner.vue`, `resources/js/Components/AppLayout.vue`, and `resources/js/Components/Topbar.vue`
- [X] T052 [P] [US4] Implement `SendCampaignRequest.php`, campaign creation and draft-to-send Actions, mandatory recipient persistence, queued recipient mail job, safe mailable, lifecycle status including expiry, and succeeded/rejected/failed/expired audit events in `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/CreateCampaignRequest.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/SendCampaignRequest.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/CreateCommunicationCampaignAction.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/SendCommunicationCampaignAction.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Jobs/SendSaasAdminCampaignJob.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Mail/SaasAdminCampaignMail.php`
- [X] T053 [US4] Add communication controller, preview/confirmation props, campaign status query, `saas-admin.campaigns.store` and `saas-admin.campaigns.send` routes in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/OperationsAdminController.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Queries/OperationsOverviewQuery.php`, and `plugins/gaeld-ee/routes/web.php`
- [X] T054 [US4] Implement export request validation, sensitivity classification, filter persistence, signed download ownership, cleanup, lifecycle Action, and succeeded/rejected/failed/expired audit events in `plugins/gaeld-ee/src/Domains/SaasAdmin/Requests/CreateExportRequest.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Actions/CreateExportRequestAction.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminExportService.php`, and `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`
- [X] T055 [P] [US4] Implement `GenerateOperationalExportJob.php`, `GenerateFinancialExportJob.php`, `GenerateCustomerDataExportJob.php`, and `ExpireSaasAdminOperationsJob.php` using existing CSV/report/export services, `SaasAdminExpiryService.php`, atomic claim/lease expiry guards, idempotent transition keys, lifecycle updates, bounded audit outcomes, and a once-per-minute scheduler registration in `plugins/gaeld-ee/src/Domains/SaasAdmin/Jobs/`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminExpiryService.php`, `plugins/gaeld-ee/src/Domains/SaasAdmin/Services/SaasAdminAuditService.php`, and `plugins/gaeld-ee/src/GaeldEEServiceProvider.php`
- [X] T056 [US4] Add export controller, status page/component, download route, and explicit expiry/error states in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/ExportAdminController.php`, `plugins/gaeld-ee/routes/web.php`, `resources/js/Components/SaasAdmin/ExportStatus.vue`, and `resources/js/Pages/SaasAdmin/Organizations.vue`
- [X] T057 [US4] Add support, campaign, export, expiry, and sensitive-data translations in `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, and `lang/it/app.php`

**Checkpoint**: Run US4 security, queue, expiry, and scope tests with multiple
organizations and a failed export fixture.

## Phase 7: Polish and Cross-Cutting Verification

- [X] T058 [P] Add architecture quality review notes and verify the checklist in `specs/002-saas-admin-console/checklists/architecture.md`, including EE-owned frontend pages/components and CE build exclusion
- [X] T059 Reconcile the final SaaS admin route/page/prop contract against the initial skeleton from T003, after T020, T028, T041, T050, T053, and T056, in `contract/app-contract.json`, ensuring no CE contract is widened
- [X] T060 [P] Add the automated four-locale SaaS admin translation-key parity test in `plugins/gaeld-ee/tests/Feature/SaasAdmin/TranslationContractTest.php`
- [X] T061 [P] Add two isolated deterministic benchmark cases for `GET /saas-admin` and `GET /saas-admin/organizations?search=Org-0420&subscription=active&health=healthy&page=1`, each using the 5,000-organization acceptance dataset, one warm-up, twenty timed repetitions in a separate process, p95 calculated from the sorted 20-sample wall-time distribution, no more than 30 database queries, and no more than 128 MB additional request memory in `plugins/gaeld-ee/tests/Feature/SaasAdmin/SaasAdminPerformanceTest.php`
- [X] T062 [P] Update EE internal documentation and release notes for the new admin workflows in `plugins/gaeld-ee/INTERNAL.md` and `CHANGELOG.md`
- [X] T063 Run focused plugin tests with `vendor/bin/sail artisan test --compact plugins/gaeld-ee/tests/Feature/SaasAdmin`
- [X] T064 Run security regression tests with `vendor/bin/sail artisan test --compact tests/Security/Auth/AuthBypassTest.php tests/Security/Billing/StripeWebhookSecurityTest.php`
- [X] T065 Run `vendor/bin/sail bin pint --format agent` on changed PHP paths; `--dirty` is unavailable inside the Sail container because Git is not mounted there
- [X] T066 Run `vendor/bin/sail bin phpstan analyse --memory-limit=2G`
- [X] T067 Run `vendor/bin/sail pnpm run build`
- [X] T068 Run the full `vendor/bin/sail artisan test --compact` suite and record unrelated pre-existing failures separately
- [X] T069 Perform the repeatable browser acceptance run from `specs/002-saas-admin-console/quickstart.md`, capturing pass/fail evidence for overview, search, detail actions, Stripe diagnostic failure, keyboard confirmation/cancellation/focus return, support banner/stop, operations, signed downloads, and all required asynchronous states; clean replay completed after final modal and fixture permission fixes
- [X] T070 Run `/speckit-analyze` and `/speckit-converge`, then reconcile any uncovered requirement before release
- [X] T071 Add and run `tests/Feature/SaasAdmin/CeInertiaCompatibilityTest.php` with `FEATURE_SAAS=false` and EE plugin loading disabled, asserting that a shared Inertia page resolves no EE class/table; the dedicated compatibility test passes, while the broader CE regression subset remains a follow-up gate

## Dependencies and Execution Order

- T001-T013 establish plugin test fixtures, persistence, authorization, audit,
  and support-session boundaries; they block all user stories.
- US1 (T014-T024) is the MVP and should complete before the overview contract is
  reused by later views.
- US2 (T025-T034) depends on the audit service and organization read contract,
  but can be developed in parallel with US3 after the foundation.
- US3 (T035-T046) depends on the read-query conventions and BillingService
  diagnostic boundary, but does not depend on support sessions.
- US4 (T047-T057) depends on audit, middleware, queue, signed URL, and export
  foundations; support and export tests can be prepared in parallel.
- T058-T071 are final gates and must run after the desired stories are merged.

## Requirement Traceability

| Requirements | Covered by |
|---|---|
| FR-001, FR-020, FR-023 | T009, T012-T013, T063-T071 |
| FR-002 through FR-008 | T014-T024, T038-T046 |
| FR-009 through FR-010 | T025-T034 |
| FR-011 through FR-014 | T035-T046 |
| FR-015 through FR-019 | T005-T013, T036-T057, T069 |
| FR-021 | T061, T066, quickstart.md Performance Acceptance |
| FR-022 | T004, T034, T046, T057, T060, T067, T069 |
| SC-001 | T061, T066, quickstart.md Performance Acceptance |
| SC-002 | T021-T022, T032, T042, T069, quickstart.md Browser Acceptance Run |
| SC-003 through SC-006 | T005-T010, T026, T030, T036-T057 |
| SC-007 | T004, T034, T046, T057, T060, T069 |
| SC-008 | T012, T014-T015, T031, T043-T044, T064, T068, T071 |

## Parallel Execution Examples

### After foundational work

```text
Track A: T014-T024 - Overview and organization discovery
Track B: T025-T034 - Organization detail and lifecycle actions
Track C: T035-T046 - Billing, health, and operations
```

### Within User Story 4

```text
Track A: T047, T050-T051 - Support sessions
Track B: T048, T052-T053 - Targeted communication
Track C: T049, T054-T056 - Exports
```

## Implementation Strategy

### MVP first

1. Complete T001-T013, including security and audit foundations.
2. Complete US1: overview and paginated organization discovery.
3. Run the US1 checkpoint with representative scale and explicit missing-data
   states. This is the first demonstrable release increment.

### Incremental delivery

1. Add US2 for support diagnosis and safe lifecycle actions.
2. Add US3 for billing, health, and operations separation.
3. Add US4 for support sessions, communication, and exports.
4. Run cross-cutting security, localization, build, static analysis, and full
   test gates.

Every user story must remain independently testable and must not weaken the
existing EE access gate or CE behavior.

## Phase 8: Convergence

- [X] T072 [US4] Add a separate campaign preview and confirmation state that shows explicit recipients, locale, subject, body, and reason before queueing delivery, and add the corresponding rejected/cancelled UI coverage per FR-017 and US4/AC3 (partial)
- [X] T073 [US4] Complete campaign and export worker fencing so claim, heartbeat, publish/send, completion, failure, expiry, and audit transitions all require the current claim token, fencing generation, valid lease, and idempotent transition key; add stale-worker and race coverage per FR-018 and plan: worker transition predicates (partial)
- [X] T074 [US4] Generate large exports in bounded chunks with cleanup on partial artifact failure and explicit `partially_failed` lifecycle/audit mapping for item-level failures across operational, financial, and customer-data exports per FR-018 and SC-006 (partial)
- [X] T075 [US1] Replace hardcoded SaaS admin Vue navigation and mutation paths with named route URLs, then reconcile the billing route name between the Vue contract and `contract/app-contract.json` per plan: route/Inertia contract (partial)
