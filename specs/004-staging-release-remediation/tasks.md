# Tasks: Staging Release Remediation

**Input**: Design documents from `specs/004-staging-release-remediation/`

**Prerequisites**: `spec.md` and `plan.md`

## Task Format

Every task follows:

```text
- [ ] T### [P] [US#] Description with an exact repository path
```

Tests are mandatory for every corrected behavior. Execute PHP and frontend
commands through Sail, except private EE commands which run in the nested EE
repository with its own tooling.

## Phase 1: API Contract and Token Security

**Goal**: Make token creation, expiration, and organization authorization
consistent across web settings and `/api/v1`.

- [x] T001 [P] [US1] Add failing feature coverage for web token creation with 7, 30, 90, and 365-day expiration in `tests/Feature/Api/ApiTokenManagementTest.php`.
- [x] T002 [P] [US1] Add failing personal and organization API token authorization tests for canonical account-read abilities and wildcard access in `tests/Feature/Api/ApiContractTest.php` and `tests/Security/Api/ApiTokenSecurityTest.php`.
- [x] T003 [P] [US1] Define one canonical token ability list and a narrow legacy colon-name compatibility map in `app/Http/Middleware/Api/TokenPermissionMap.php`.
- [x] T004 [US1] Derive web/API token ability validation and settings props from the canonical permission map in `app/Domains/Api/Requests/StorePersonalTokenSettingsRequest.php`, `app/Domains/Api/Requests/StoreApiTokenRequest.php`, `app/Domains/Api/Controllers/TokenSettingsController.php`, and `app/Domains/Api/Controllers/ApiTokenController.php`.
- [x] T005 [US1] Correct personal and organization token ability evaluation while preserving organization scope in `app/Http/Middleware/EnsureApiOrganization.php` and `app/Domains/Api/Models/PersonalAccessToken.php`.
- [x] T006 [US1] Cast validated expiration durations to integers before Carbon arithmetic in `app/Domains/Api/Controllers/TokenSettingsController.php`, `app/Domains/Api/Controllers/ApiTokenController.php`, and `app/Domains/Api/Controllers/OrgTokenController.php`.
- [x] T007 [US1] Update API token docs and contract examples to the canonical vocabulary and expiration behavior in `contract/api-contract.json`, `README.md`, and `specs/004-staging-release-remediation/quickstart.md` if created.
- [x] T008 [US1] Run focused token feature/security tests and verify all supported expirations through `vendor/bin/sail artisan test --compact tests/Feature/Api/ApiTokenManagementTest.php tests/Feature/Api/ApiContractTest.php tests/Security/Api/ApiTokenSecurityTest.php`.

## Phase 2: Exact Expense Ledger Posting

**Goal**: Ensure a VAT-bearing expense credits the exact gross amount and only
uses the configured Swiss rounding account when the rounding policy requires it.

- [x] T009 [P] [US2] Add failing ExpenseService tests for exact net/VAT/gross lines, CHF rounding boundaries, and no unexplained revenue residuals in `tests/Unit/Services/ExpenseServiceTest.php`.
- [x] T010 [P] [US2] Add failing web/API expense posting assertions for bank balance and linked ledger lines in `tests/Feature/Expenses/ExpenseFlowTest.php` and `tests/Feature/Api/BusinessDocumentApiTest.php`.
- [x] T011 [US2] Correct gross amount and Swiss rounding behavior in `app/Domains/Expenses/Services/ExpenseService.php` and `app/Support/SwissRounding.php` only where tests prove a helper defect.
- [x] T012 [US2] Verify reconciliation expense posting uses the same exact amount contract in `app/Domains/Banking/Services/ExpenseReconciler.php` and related tests.
- [x] T013 [US2] Run focused expense, reconciliation, and API tests through `vendor/bin/sail artisan test --compact tests/Unit/Services/ExpenseServiceTest.php tests/Feature/Expenses/ExpenseFlowTest.php tests/Feature/Api/BusinessDocumentApiTest.php`.

## Phase 3: Payroll Calculation Consistency

**Goal**: Make payroll preview, generated salary slips, and eventual ledger
posting use one identical calculation result.

- [x] T014 [P] [US2] Add failing tests comparing payroll preview data and generated salary-slip deductions/net salary for an employee with default rates in `tests/Feature/Payroll/PayrollFlowTest.php`.
- [x] T015 [P] [US2] Add unit coverage for deduction-rate defaults, custom rates, and cent rounding in `tests/Unit/Services/SwissDeductionServiceTest.php`.
- [x] T016 [US2] Centralize or reuse the `PayrollCalculator` result between preview and generation in `app/Domains/Payroll/Services/PayrollCalculator.php`, `app/Domains/Payroll/Controllers/PayrollRunController.php`, and `app/Domains/Payroll/Actions/GeneratePayrollRunAction.php`.
- [x] T017 [US2] Ensure `SalarySlip` persistence and `PostPayrollAction` consume the same deduction fields without recomputing values in `app/Domains/Payroll/Models/SalarySlip.php` and `app/Domains/Payroll/Actions/PostPayrollAction.php`.
- [x] T018 [US2] Run focused Payroll tests through `vendor/bin/sail artisan test --compact tests/Feature/Payroll/PayrollFlowTest.php tests/Unit/Services/SwissDeductionServiceTest.php`.

## Phase 4: Cash Flow and Reconciliation Contracts

**Goal**: Make financial report totals mathematically coherent and eliminate
avoidable reconciliation contract warnings.

- [x] T019 [P] [US2] Add failing cash-flow tests for operating/investing/financing totals, actual cash reconciliation, and the expense/payment scenario in `tests/Feature/Reporting/CashFlowReportTest.php`.
- [x] T020 [P] [US3] Add failing frontend contract fixtures/tests for empty and populated reconciliation props in `tests/Feature/Banking/ReconciliationTest.php` or the repository's existing frontend contract test surface.
- [x] T021 [US2] Correct indirect cash-flow reconciliation and returned subtotals in `app/Domains/Reporting/Services/ReportingService.php`.
- [x] T022 [US3] Align the reconciliation Inertia response/schema and normalize valid paginator/model data without warnings in `app/Domains/Banking/Controllers/ReconciliationController.php` and `resources/js/lib/inertiaContracts.js`.
- [x] T023 [US3] Update reconciliation UI accessors and states if required by the corrected contract in `resources/js/Pages/Banking/ReconciliationShow.vue`.
- [x] T024 [US2] Run focused reporting and reconciliation tests through `vendor/bin/sail artisan test --compact tests/Feature/Reporting/CashFlowReportTest.php tests/Feature/Banking`.

## Phase 5: Frontend Reliability and Mobile Layout

**Goal**: Remove the Maska warning and make Settings usable at 375px without
horizontal page overflow.

- [x] T025 [P] [US3] Locate all Maska directives/components and add a focused browser or component regression check in `resources/js` and the nearest existing frontend test surface.
- [x] T026 [P] [US3] Add a mobile layout assertion or documented Playwright check for Settings tabs at 375px in `resources/js/Pages/Settings/ApiTokens.vue` and the owning Settings page.
- [x] T027 [US3] Update the Maska component/directive usage to expose the required API without changing field validation in the affected `resources/js` components.
- [x] T028 [US3] Make Settings tabs responsive and keyboard-navigable at narrow widths in the owning Settings Vue page/component.
- [x] T029 [US3] Run the CE frontend build and targeted browser/contract checks through `vendor/bin/sail pnpm run build`.

## Phase 6: CE/EE Release Coordination

**Goal**: Prepare CE v3.6 corrective metadata and private EE v2.8 metadata while
keeping proprietary code out of CE.

- [x] T030 [P] [US4] Add corrective CE release notes and staging/rollback references in `CHANGELOG.md`, `RELEASE.md`, and `docs/qa/two-year-e2e-20260822.md`.
- [x] T031 [P] [US4] Update the private EE manifest and changelog to approved v2.8.0 in `plugins/gaeld-ee/plugin.json` and the private EE release documentation.
- [x] T032 [US4] Run the CE checks, public boundary check, API contract parse, and clean CE build from Sail.
- [ ] T033 [US4] Run private EE tests, static analysis, and frontend build from `plugins/gaeld-ee`.
- [ ] T034 [US4] Redeploy the corrected CE/EE pair to staging with immutable refs and repeat the focused API, finance, onboarding, and responsive smoke tests.
- [ ] T035 [US4] Complete the remaining multi-user, archive, long-year close, second-year, and reopen scenarios only after P1 financial/API gates pass, recording results in `docs/qa/two-year-e2e-20260823.md`.
- [x] T036 [US4] Run full CE PHPUnit, Pint, PHPStan, and frontend build checks and verify no production branch or tag changed.

## Dependencies and Execution Order

- T001-T008 establish the API contract and are the first blocker.
- T009-T018 cover Expense and Payroll independently after the API tests are
  added; T009-T013 and T014-T018 can proceed in parallel after setup.
- T019-T024 depend on the exact ledger behavior and then establish report and
  reconciliation contracts.
- T025-T029 can proceed in parallel with Phase 4 where files do not overlap.
- T030-T036 are release gates and require all corrective behavior to pass.
- EE metadata and private tests remain separate from CE code and are never
  staged into the public repository.

## Independent Test Criteria

- **US1**: Personal/org tokens authorize permitted account reads, reject
  forbidden calls, and support all expiration options without 500 responses.
- **US2**: Expense, Payroll, VAT, reports, and cash movement reconcile exactly
  between UI, persisted ledger, and report totals.
- **US3**: Reconciliation props produce no warning and Settings has no page-level
  horizontal overflow at 375px, 768px, or 1440px.
- **US4**: CE and EE immutable refs can be deployed together to staging while
  production remains unchanged and CE contains no private source.

## Implementation Strategy

1. Fix and test API authorization/expiration first because integrations are a
   release-critical CE feature.
2. Fix financial calculation consistency next; do not post further questionable
   staging payroll or expense records until the focused tests pass.
3. Correct contract and responsive defects, then run the full CE gates.
4. Version the private plugin as EE v2.8.0 only after its own checks pass.
5. Redeploy staging and repeat the blocked journeys before any production MR or
   production deploy.
