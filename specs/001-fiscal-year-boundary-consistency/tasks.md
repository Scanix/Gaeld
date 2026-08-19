---

description: "Gäld task list for fiscal-year boundary consistency"

---

# Tasks: Fiscal-Year Boundary Consistency

**Input**: [spec.md](spec.md) and [plan.md](plan.md)

**Scope rule**: Use the existing `FiscalYear`, `FiscalYearService`, reporting,
archive, export, and closing paths. Do not add a repository layer, generic date
framework, or second fiscal-year model.

## Phase 1: Setup

- [x] T001 [P] Add focused test classes for period resolution and fiscal-year consumers in `tests/Unit/Accounting/FiscalYearPeriodTest.php`, `tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php`, `tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php`, and `tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php`

## Phase 2: Foundational Contract and Security

- [x] T002 Add the nullable `fiscal_year_id` foreign key and organization/fiscal-year index to `database/migrations/2026_08_19_222824_add_fiscal_year_id_to_legal_archives_table.php`
- [x] T003 [P] Add the `fiscalYear()` relationship and PHPDoc to `app/Domains/Accounting/Models/LegalArchive.php`
- [x] T004 [P] Add resolver tests for explicit ranges, inclusive boundaries, legacy fallback, invalid IDs, and organization isolation in `tests/Unit/Accounting/FiscalYearPeriodTest.php`
- [x] T005 Implement the immutable `FiscalYearPeriod` value object in `app/Domains/Accounting/DTOs/FiscalYearPeriod.php`
- [x] T006 Implement organization-scoped period resolution with explicit `fiscal_year_id` precedence and legacy year fallback in `app/Domains/Accounting/Services/FiscalYearService.php`
- [x] T007 Add request validation tests for explicit fiscal-year selection and legacy compatibility in `tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php`
- [x] T008 Update `app/Domains/Reporting/Requests/GenerateAccountingExportRequest.php` and `app/Domains/Accounting/Requests/StoreYearEndClosingRequest.php` so explicit fiscal-year IDs are validated without weakening tenant checks

## Phase 3: User Story 1 - Use the selected fiscal year consistently (Priority: P1)

**Independent Test**: An 18-month fiscal year includes records on both
boundaries, excludes the following day's records, and displays the same dates
used by reports and exports.

### Tests First

- [x] T009 [P] [US1] Add report and Inertia period assertions for an 18-month fiscal year in `tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php`
- [ ] T010 [P] [US1] Add export request, queued-job payload, generated ZIP boundary, and concurrent-request assertions in `tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php`

### Implementation

- [x] T011 [US1] Resolve selected fiscal-year records and expose period options and exact dates from `app/Domains/Reporting/Controllers/AccountingExportController.php`
- [x] T012 [US1] Update `resources/js/Pages/Accounting/Export.vue` to submit explicit `fiscal_year_id` values and display the resolved date range without adding a second period selector
- [x] T013 [US1] Update `app/Domains/Reporting/Jobs/GenerateAccountingExportJob.php` and `app/Domains/Reporting/Services/AccountingExportService.php` to resolve explicit periods while accepting legacy queued year payloads
- [x] T014 [US1] Replace calendar-year report/export ranges with the resolved period in `app/Domains/Reporting/Controllers/ReportController.php` and `app/Domains/Reporting/Services/AccountingExportService.php`
- [x] T015 [US1] Confirm the existing Inertia report period props and translation keys are sufficient; no contract normalizer or new translation files are required

**Checkpoint**: Run the focused US1 tests and inspect one generated ZIP before
starting archive and closing changes.

## Phase 4: User Story 2 - Close and archive the complete fiscal period (Priority: P1)

**Independent Test**: An 18-month closed period archives every in-range
document exactly once, excludes the next day's document, and evaluates VAT
period status without creating a partial settlement.

### Tests First

- [x] T016 [P] [US2] Add archive inclusion, exclusion, idempotency, lock-contract, provenance, and organization-isolation tests in `tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php`
- [x] T017 [P] [US2] Add PDF/report date assertions and archive regeneration safety tests in `tests/Feature/Accounting/ArchivePdfGenerationTest.php`
- [x] T018 [P] [US2] Add closing tests for unresolved complete VAT periods that overlap a fiscal year in `tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php`

### Implementation

- [x] T019 [US2] Pass the resolved period into `app/Domains/Accounting/Services/LegalArchivingService.php` and select invoice, expense, journal, and salary records by inclusive fiscal dates
- [x] T020 [US2] Persist fiscal-year provenance for new archives, preserve historical files, and serialize concurrent generation with an organization-period lock in `app/Domains/Accounting/Actions/GenerateArchivePdfAction.php` and `app/Domains/Accounting/Services/LegalArchivingService.php`
- [x] T021 [US2] Resolve the selected period and expose exact dates from `app/Domains/Accounting/Controllers/LegalArchiveController.php`
- [x] T022 [US2] Update `resources/js/Pages/Accounting/Archives/Index.vue` to use the explicit period identity and show the resolved date range in archive actions
- [x] T023 [US2] Update `app/Domains/Accounting/Actions/YearEndClosingAction.php` and `app/Domains/Accounting/Controllers/YearEndClosingController.php` to use the resolved period for closing accounts and complete overlapping VAT settlement checks without adding due-date state
- [x] T024 [US2] Update `resources/js/Pages/Accounting/YearEndClosing.vue` to submit and display the explicit fiscal-year period without duplicating accounting rules

**Checkpoint**: Run the focused archive and closing tests, then validate the
long-year journey described in the plan's validation runbook.

## Phase 5: User Story 3 - Preserve legacy organization behavior (Priority: P2)

**Independent Test**: An organization without explicit fiscal-year records keeps
the existing 01-01 to 12-31 report, export, archive, and closing behavior.

- [x] T025 [P] [US3] Add legacy calendar-year regression assertions in `tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php` and `tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php`
- [x] T026 [US3] Add backward-compatible handling for old export jobs and year-based archive URLs in `app/Domains/Reporting/Jobs/GenerateAccountingExportJob.php`, `app/Domains/Accounting/Controllers/LegalArchiveController.php`, and `routes/web/accounting.php`
- [x] T027 [US3] Add migration compatibility coverage for historical `legal_archives` rows with null `fiscal_year_id` in `tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php`

## Final Verification

- [x] T028 [P] Run the focused feature tests with `vendor/bin/sail artisan test --compact tests/Unit/Accounting/FiscalYearPeriodTest.php tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php`
- [x] T029 Run `vendor/bin/sail bin pint --dirty --format agent` for modified PHP files
- [x] T030 Run `vendor/bin/sail bin phpstan analyse --memory-limit=2G`
- [x] T031 Run `vendor/bin/sail pnpm run build`
- [x] T032 Run the full `vendor/bin/sail artisan test --compact` suite and record any pre-existing failures separately
- [ ] T033 Review the spec, plan, tasks, code, and current Git diff together; run `/speckit-converge` before release planning

## Dependencies and Execution Order

- T001-T008 establish the shared period contract and tenant-safe inputs.
- US1 (T009-T015) must complete before archive/export consumers are reused by
  US2 and before the frontend period payload is considered stable.
- US2 (T016-T024) depends on the resolver and explicit period contract, but its
  archive tests can be prepared in parallel with US1 implementation.
- US3 (T025-T027) runs after the first consumer changes so legacy fallback is
  tested against the actual compatibility path.
- T028-T033 are final gates; no release work begins while a focused or full test
  failure is unexplained.

## Implementation Strategy

Deliver US1 first: one resolver, one explicit period payload, and correct report
and export ranges. Stop and validate before touching legal archives and closing.
Then deliver US2, followed by legacy compatibility tests. Do not extract
controllers or refactor unrelated Stripe/SaaS code in this feature.