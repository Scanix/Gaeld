# Implementation Plan: Fiscal-Year Boundary Consistency

**Branch**: `001-fiscal-year-boundary-consistency` | **Date**: 2026-08-19 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-fiscal-year-boundary-consistency/spec.md`

## Summary

Use the existing `FiscalYear` record as the source of truth for an explicit
inclusive start and end date. Resolve the selected fiscal year once at each
request/job boundary, pass the resolved range to existing reporting, archive,
PDF, export, and closing services, and retain the legacy calendar-year fallback
for organizations without an explicit record.

Add one small immutable `FiscalYearPeriod` boundary object because the same
range and display identity cross multiple domains. Do not introduce a new
repository, accounting engine, package, or parallel fiscal-year model.

## Technical Context

**Language/Runtime**: PHP 8.4, Laravel 13

**Frontend**: Inertia.js v3, Vue 3, Vite, Tailwind CSS

**Storage/Infrastructure**: PostgreSQL, Redis, Horizon, filesystem storage as
used by the existing feature

**Testing**: PHPUnit through `vendor/bin/sail artisan test`; relevant frontend
build or component checks through `vendor/bin/sail pnpm`

**Project Type**: Existing Laravel web application with domain-driven modules,
Inertia pages, CE/EE feature flags, and organization tenancy

**Performance and Scale**: Preserve current queue-based accounting exports and
existing archive behavior. Boundary resolution must add one fiscal-year lookup
per request/job, and archive/export queries must remain organization-scoped.

**Constraints**: Inclusive date boundaries; no partial VAT settlements; VAT
periods remain independent from financial fiscal years; closed years remain
immutable; explicit organization authorization and tenant isolation; legacy
calendar-year fallback; existing archive files remain readable; no dashboard or
tax-declaration redesign in this feature.

## Existing Codebase Impact

### Domain Ownership

- **Owning domain**: Accounting, with Reporting as the read-only consumer.
- **Invariants preserved**: Inclusive fiscal-year membership, organization
	isolation, balanced ledger entries, immutable closed/archive records, and
	independent VAT period boundaries.
- **Existing services/actions to reuse**: `FiscalYearService`,
	`YearEndClosingAction`, `ReportingService`, `LegalArchivingService`,
	`GenerateArchivePdfAction`, `AccountingExportService`, and existing ledger
	query services.
- **Existing documentation and specs consulted**: [Product baseline](../000-product-baseline/current-state.md),
	[architecture map](../000-product-baseline/architecture-map.md),
	[divergence register](../000-product-baseline/divergence-register.md), and
	[Gäld constitution](../../.specify/memory/constitution.md).

### Backend Surfaces

```text
app/Domains/Accounting/DTOs/FiscalYearPeriod.php
app/Domains/Accounting/Services/FiscalYearService.php
app/Domains/Accounting/Actions/YearEndClosingAction.php
app/Domains/Accounting/Actions/GenerateArchivePdfAction.php
app/Domains/Accounting/Services/LegalArchivingService.php
app/Domains/Accounting/Controllers/YearEndClosingController.php
app/Domains/Reporting/Services/AccountingExportService.php
app/Domains/Reporting/Jobs/GenerateAccountingExportJob.php
app/Domains/Reporting/Controllers/AccountingExportController.php
app/Domains/Reporting/Requests/GenerateAccountingExportRequest.php
routes/web/accounting.php
database/migrations/                     # nullable fiscal-year archive link
```

Remove unused paths from the delivered plan and replace placeholders with
actual files.

### Frontend Surfaces

```text
resources/js/Pages/Accounting/Export.vue
resources/js/Pages/Accounting/Archives/Index.vue
resources/js/Pages/Accounting/YearEndClosing.vue
resources/js/lib/inertiaContracts.js
lang/{en,fr,de,it}/                       # period and status translations
```

The dashboard's calendar-year display selection and the feature-gated tax
declaration workflow remain follow-up contracts. They are not silently changed
by this feature.

## Constitution Check

Before implementation, confirm:

- [x] The owning domain and accounting invariants are explicit.
- [x] Existing Actions, Services, DTOs, Requests, Policies, and components were checked first.
- [x] Organization scope is enforced in model queries, raw queries, and validation rules as appropriate.
- [x] Authentication, authorization, validation, and failure behavior are specified.
- [x] The design adds only one small boundary object justified by cross-domain range duplication.
- [x] Tests cover the acceptance scenarios, including relevant failure and tenant-isolation paths.
- [x] Migration, rollback, compatibility, and release impact are addressed.

## Data and Contract Changes

**Data model**: Add a nullable `fiscal_year_id` foreign key to `legal_archives`
for new explicit-period archives. Keep the existing integer `fiscal_year` as a
backward-compatible display label. Preserve historical archive rows and files;
backfill only when an existing row maps unambiguously to one fiscal-year record.

Add transient `FiscalYearPeriod` data containing the organization ID, optional
fiscal-year ID, display label, inclusive `fromDate`, inclusive `toDate`, and a
legacy-fallback flag. It is not a second persisted fiscal-year entity.

**HTTP/Inertia/API contract**: Fiscal-year selection accepts an explicit
`fiscal_year_id` where available and retains a validated four-digit legacy year
fallback. Inertia pages receive the selected period's ID, label, start date, and
end date. Export and archive download links carry the explicit period identity
when available; existing year-based links remain readable through fallback
resolution.

**Frontend states**: Show the selected period dates, loading state while an
export is queued, empty/header-only results, validation errors, forbidden
responses, archive-present state, and idempotent repeat-generation feedback.

## Test Strategy

List tests by behavior, not only by class:

- **Feature/integration**: Add
	`tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php`,
	`tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php`, and
	`tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php`.
- **Unit/domain**: Add
	`tests/Unit/Accounting/FiscalYearPeriodTest.php` and focused VAT-boundary
	tests for the closing period resolver.
- **Frontend/build**: Run the Vite build and add Inertia prop assertions to the
	relevant feature tests; no separate frontend unit framework is introduced.
- **Manual/browser**: Follow [quickstart.md](quickstart.md) with an 18-month
	fiscal year and boundary-date records on the current `develop` build.

All new behavior must be verifiable through the repository's Sail workflow.

## Project Structure

```text
specs/001-fiscal-year-boundary-consistency/
├── spec.md
├── checklists/requirements.md
├── plan.md
├── research.md
├── data-model.md
├── contracts/inertia.md
├── quickstart.md
└── tasks.md                    # generated only after plan approval
```

## Rollout and Operations

- **Migration/backfill**: Add nullable `legal_archives.fiscal_year_id`; preserve
	old rows and only backfill unambiguous matches. Rollback drops only the new
	nullable column after any deployment rollback decision is approved.
- **Feature flag**: None. This corrects the existing fiscal-year contract.
- **Queue/scheduler/storage impact**: Keep queued export jobs compatible with
	legacy year payloads; new jobs carry the explicit fiscal-year ID. New files
	use a stable explicit-period key where available; existing paths remain
	readable.
- **Monitoring and rollback**: Log resolved period ID and dates for exports,
	archives, and closing. Compare boundary fixture counts before rollout. A
	rollback must not delete or rewrite immutable archive files.
- **Documentation/changelog**: Update the accounting domain README and
	changelog if user-visible export or closing behavior changes.

## Complexity Tracking

Record only justified deviations from the constitution:

| Deviation | Why it is needed | Simpler alternative rejected because |
|---|---|---|
| One small transient period boundary object | The same explicit date range is consumed by multiple domains and jobs | Passing uncoordinated year/from/to combinations is the source of the current divergence |