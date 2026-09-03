# Tasks: Gäld Offer Alignment

## Phase 1: Contract and fixtures

- [X] T001 Create canonical offer fixtures for Cloud Free, Solo, Team, legacy Starter, and legacy Business in `plugins/gaeld-ee/tests/Feature/Billing/Concerns/CreatesOfferFixtures.php`.
- [X] T002 [P] Add offer and migration test cases covering canonical/public status, stable internal slugs, and legacy plan visibility in `tests/Feature/OfferPlanDefinitionTest.php`.
- [X] T003 [P] Add a four-locale pricing-key parity test for the website messages in `../web/src/__tests__/pricing-contract.test.ts`.
- [X] T004 Update `specs/005-offer-alignment/spec.md` and `specs/005-offer-alignment/plan.md` with any approved Solo/Team entitlement evidence before implementation begins.

## Phase 2: Plan persistence and legacy compatibility

- [X] T005 Add reversible plan metadata and quota columns for monthly OCR, document storage, and public/legacy availability in `plugins/gaeld-ee/migrations/2026_09_02_182246_add_offer_alignment_to_ee_plans.php`.
- [X] T006 Update `plugins/gaeld-ee/src/Domains/Billing/Models/Plan.php` casts, PHPDoc, public/legacy helpers, and entitlement accessors for the new fields.
- [X] T007 [P] Add canonical Cloud Free/Solo/Team records and mark legacy Starter/Business unavailable for new selection without deleting their rows in `plugins/gaeld-ee/migrations/2026_09_02_182246_add_offer_alignment_to_ee_plans.php`.
- [X] T008 Implement an idempotent legacy subscription migration/backfill for existing Free, Starter, and Business assignments in `plugins/gaeld-ee/src/Domains/Billing/Actions/MigrateLegacyOfferSubscriptionsAction.php`.
- [ ] T009 Add migration tests for legacy Free quota conversion, legacy paid price/Stripe preservation, admin-granted subscriptions, reruns, and interrupted/repeated execution in `plugins/gaeld-ee/tests/Feature/Billing/OfferMigrationTest.php`.
- [X] T010 Add a safe operational command or job for the migration with dry-run and summary output in `plugins/gaeld-ee/src/Domains/Billing/Commands/MigrateOfferPlansCommand.php`.

## Phase 3: Quota and entitlement foundation

- [X] T011 [P] [US2] Add the CE-safe organization quota contract and default resolver in `app/Support/Contracts/OrganizationQuotaResolver.php` and `app/Support/Services/DefaultOrganizationQuotaResolver.php`.
- [X] T012 [US2] Bind the default resolver in `app/Providers/AppServiceProvider.php` and the subscription-backed implementation in `plugins/gaeld-ee/src/GaeldEEServiceProvider.php`.
- [X] T013 [US2] Implement the EE subscription-backed quota resolver for users, invoices, daily/monthly OCR, document storage, and feature checks in `plugins/gaeld-ee/src/Support/SubscriptionOrganizationQuotaResolver.php`.
- [X] T014 [P] [US2] Add the per-organization document-storage usage migration, model, and service in `database/migrations/2026_09_02_182853_create_organization_document_storage_usages_table.php`, `app/Domains/Organizations/Models/OrganizationDocumentStorageUsage.php`, and `app/Domains/Organizations/Services/OrganizationDocumentStorageService.php`.
- [X] T015 [US2] Add an idempotent storage usage backfill command and tests for receipts, invoice justificatifs, exclusions, deletes, and reruns in `app/Console/Commands/BackfillOrganizationDocumentStorageCommand.php` and `tests/Feature/Organizations/DocumentStorageQuotaTest.php`.
- [X] T016 [US2] Apply atomic document-storage checks and cleanup to receipt and invoice document upload/delete boundaries in `app/Domains/Expenses/Controllers/ExpenseReceiptController.php`, `app/Domains/Expenses/Controllers/ExpenseController.php`, `app/Domains/Invoicing/Controllers/InvoiceController.php`, `app/Domains/Invoicing/Controllers/InvoiceDocumentController.php`, and the owning upload service.
- [X] T017 [US2] Add monthly OCR reservation and displayed-period support while preserving existing daily quota behavior for legacy plans in `app/Domains/Expenses/Controllers/ExpenseReceiptController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, and `resources/js/Components/QuickReceiptButton.vue`.
- [X] T018 [P] [US2] Add invoice, OCR, storage, and quota-boundary tests for Cloud Free, Solo, Team, and CE mode in `tests/Feature/Invoicing/InvoiceFlowTest.php`, `tests/Feature/Expenses/ScanReceiptTest.php`, and `tests/Feature/Organizations/DocumentStorageQuotaTest.php`.
- [X] T019 [US2] Enforce Solo/Team member limits through the quota resolver and add Cloud Free/Solo/Team invitation boundary coverage in `app/Domains/Organizations/Services/InvitationService.php` and `tests/Feature/Organizations/MemberQuotaTest.php`.
- [X] T020 [US2] Update SaaS feature resolution and API authorization tests so Cloud Free/Solo are denied advanced features/API and Team is allowed while CE behavior remains unchanged in `plugins/gaeld-ee/src/Support/SubscriptionFeatureResolver.php`, `tests/Security/Authorization/FeatureFlagEnforcementTest.php`, and `tests/Security/Api/ApiTokenSecurityTest.php`.

## Phase 4: Signup, trial, and billing lifecycle [US1]

- [X] T021 Update new-plan selection to expose only Cloud Free, Solo, and Team and reject inactive legacy plan submissions in `plugins/gaeld-ee/src/Domains/Billing/Controllers/RegistrationController.php`.
- [X] T022 Implement direct Cloud Free signup and cardless Solo/Team trial signup while preserving organization setup and chart-of-accounts seeding in `plugins/gaeld-ee/src/Domains/Billing/Controllers/RegistrationController.php`.
- [X] T023 Implement no-card Solo and Team trial creation and onboarding redirect without creating a paid Stripe subscription in `plugins/gaeld-ee/src/Domains/Billing/Controllers/RegistrationController.php`.
- [X] T024 Add an idempotent Solo/Team trial expiry fallback action that assigns Cloud Free without data loss or charge in `plugins/gaeld-ee/src/Domains/Billing/Actions/FallbackExpiredPaidTrialAction.php` and `plugins/gaeld-ee/src/Domains/Billing/Jobs/ExpirePaidTrialsJob.php`.
- [X] T025 Register the trial-expiry job in the EE scheduler and make middleware/reconciliation safe when expiry races with explicit paid conversion in `plugins/gaeld-ee/src/GaeldEEServiceProvider.php` and `plugins/gaeld-ee/src/Domains/Billing/Services/BillingService.php`.
- [X] T026 Add registration, no-charge Solo/Team trial, expiry fallback, inactive-plan rejection, and legacy signup regression tests in `plugins/gaeld-ee/tests/Feature/Billing/RegistrationTest.php`, `tests/Feature/Billing/TrialFallbackTest.php`, and `tests/Feature/OfferPlanDefinitionTest.php`.
- [X] T027 Update Billing server props and Inertia UI to distinguish current canonical plans, legacy subscriptions, trial state, quotas, explicit paid conversion, and Cloud Free fallback in `plugins/gaeld-ee/src/Domains/Billing/Controllers/BillingController.php`, `plugins/gaeld-ee/resources/js/Pages/Billing/Plans.vue`, and `app/Http/Middleware/HandleInertiaRequests.php`.
- [X] T028 Add billing lifecycle tests proving legacy plans retain price/history and explicit changes update one Stripe subscription in `plugins/gaeld-ee/tests/Feature/Billing/PlanChangeTest.php` and `plugins/gaeld-ee/tests/Feature/Billing/StripeWebhookLifecycleTest.php`.

## Phase 5: Legacy subscription migration [US3]

- [X] T029 Add a controlled legacy-plan display and explicit transition path in `plugins/gaeld-ee/resources/js/Pages/Billing/Plans.vue`, `plugins/gaeld-ee/resources/js/Components/LegacyPlanNotice.vue`, and the relevant four-locale translations.
- [X] T030 Add administrator migration reporting, dry-run output, and audit-safe summaries in `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/BillingAdminController.php` and `plugins/gaeld-ee/src/Domains/Billing/Commands/MigrateOfferPlansCommand.php`.
- [ ] T031 [P] Add security tests proving crafted inactive-plan identifiers cannot be selected and one tenant cannot affect another tenant's subscription in `plugins/gaeld-ee/tests/Security/Billing/OfferMigrationSecurityTest.php`.
- [ ] T032 Document forward migration, rollback posture, legacy price protection, and Stripe operator steps in `plugins/gaeld-ee/INTERNAL.md` and `RELEASE.md`.

## Phase 6: Website and localized public copy [US4]

- [X] T033 [US4] Update the Next.js pricing cards, plan names, prices, quotas, CTA behavior, and structured JSON-LD in `../web/src/app/[locale]/pricing/page.tsx`.
- [X] T034 [US4] Update localized website pricing messages, metadata, Cloud Free quota copy, Solo/Team trial copy, and CE/self-hosting distinction in `../web/messages/en.json`, `../web/messages/fr.json`, `../web/messages/de.json`, and `../web/messages/it.json`.
- [X] T035 [P] [US4] Add website pricing contract assertions for all four offers and absence of retired pricing keys in `../web/src/__tests__/pricing-contract.test.ts`.
- [X] T036 [US4] Add or update localized website Playwright coverage for desktop/mobile pricing cards, links, and readable quota text in `../web/tests/pricing.spec.ts`.

## Phase 7: Documentation and contract alignment [US4]

- [X] T037 [US4] Update the public plan table, trial fallback, Cloud Free limits, cancellation, export, and CE/SaaS distinction in `../docs/docs/billing.md`.
- [X] T038 [US4] Update SaaS onboarding instructions and remove stale Starter/Business/three-user/no-card contradictions in `../docs/docs/getting-started-saas.md`.
- [X] T039 [P] [US4] Reconcile localized documentation copies and generated navigation/search inputs for the four supported locales in `../docs/i18n/` where those pages are maintained separately.
- [X] T040 [US4] Update the application contract with canonical plan props, quota periods, trial fallback, and legacy-plan compatibility in `contract/app-contract.json` and `../web/contract/app-contract.json`.
- [X] T041 [US4] Add a repeatable stale-offer scan and documentation build check covering old prices, names, quotas, and trial claims in `../docs` and `../web` package scripts or their existing test locations.

## Phase 8: Staging rollout and cross-cutting verification

- [ ] T042 [P] Run focused EE billing, migration, trial, quota, and security tests through Sail and record unrelated failures separately in `plugins/gaeld-ee/tests/Feature/Billing/` and `plugins/gaeld-ee/tests/Security/`.
- [ ] T043 [P] Run core invoice, OCR, storage, member, API, and CE compatibility tests through Sail in `tests/Feature/` and `tests/Security/`.
- [ ] T044 Run Pint and PHPStan on changed PHP paths through Sail using `pint.json` and `phpstan.neon`.
- [X] T045 Run the API frontend build, website tests/build, and documentation build with the repository-supported commands in `package.json`, `../web/package.json`, and `../docs/package.json`.
- [ ] T046 Perform staging acceptance for Cloud Free signup, Solo/Team cardless trials, explicit payment conversion, expiry fallback, quotas, API denial, legacy plan display, export, and all four locales using `scripts/qa/staging-runner.mjs`.
- [ ] T047 Record Cloud Free incremental cost, storage, OCR, abuse, conversion, support, and migration metrics for the first observation period in `../wiki/api/OFFER_READINESS_CHECKLIST.md`.
- [ ] T048 [P] Update the offer readiness checklist and release notes with the actual plan records, Stripe price IDs, operational evidence, rollout date, and rollback reference in `../wiki/api/OFFER_READINESS_CHECKLIST.md` and the applicable changelogs.

## Dependencies and Execution Order

- Phase 1 establishes fixtures and the contract; it blocks all implementation phases.
- Phase 2 establishes canonical plans and legacy compatibility; it blocks public plan selection and trial changes.
- Phase 3 establishes server-side quotas and feature boundaries; it blocks reliable Cloud Free publication.
- Phase 4 implements the primary customer signup and trial journey; it depends on canonical plans and quota resolution.
- Phase 5 completes legacy customer handling; it depends on migration-safe plan records and billing lifecycle tests.
- Phases 6 and 7 can proceed in parallel after the offer contract is stable, but publication waits for server props and behavior to be verified.
- Phase 8 is the release gate and must run after the desired product and public-surface phases are complete.

## Parallel Execution Examples

### After Phase 1

```text
Track A: T005-T010 - Plan schema, canonical records, and legacy migration
Track B: T011-T015 - Quota contract, storage usage, and backfill design
Track C: T033-T041 - Website/documentation copy preparation against the frozen matrix
```

### After Phase 3

```text
Track A: T021-T028 - Signup, paid-plan trials, fallback, and Billing
Track B: T029-T032 - Legacy customer transition and operator controls
Track C: T033-T041 - Website, localization, documentation, and contracts
```

### Final verification

```text
Track A: T042-T044 - PHP tests, formatting, and static analysis
Track B: T035-T036, T045 - Website tests and build
Track C: T037-T041, T045 - Documentation and stale-claim validation
```

## Implementation Strategy

### MVP first

The minimum valuable increment is Phase 2 plus the Cloud Free/Solo/Team trial
path in Phase 4: customers can choose Cloud Free, Solo, or Team, quotas are
server-enforced, and paid-plan trials return to Cloud Free without a charge. Do not publish the new
prices until the migration and quota tests pass.

### Incremental delivery

1. Freeze the offer and fixture the legacy cases.
2. Introduce canonical plan records without deleting legacy records.
3. Add quota resolution and storage/OCR metering.
4. Release signup and trial fallback behind the existing registration controls.
5. Align Billing and the public surfaces.
6. Run staging and economic observation before broad acquisition.

## Definition of Done

- Every task has a focused test or documented reason why a test is not applicable.
- Cloud Free, Solo, Team, and CE behavior matches the approved matrix.
- Legacy subscriptions retain their agreed price and history.
- Solo and Team trials create no paid charge and return to Cloud Free when unconverted.
- Quotas are server-enforced and displayed with the correct period.
- Website, app, docs, contracts, translations, and JSON-LD contain no stale active offer.
- CE builds without EE sources.
- Focused tests, formatting, static analysis, frontend builds, docs build, and staging acceptance are recorded.
