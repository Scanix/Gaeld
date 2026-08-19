# Quickstart: Fiscal-Year Boundary Consistency

This guide validates the feature against a long fiscal year and a legacy
calendar-year organization. It is intentionally a validation guide, not an
implementation recipe.

## Prerequisites

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate:fresh --seed
```

Use a test organization with accounting permissions and a separate second
organization for tenant-isolation checks.

## Automated Validation

After implementation, run the focused tests first:

```bash
vendor/bin/sail artisan test --compact \
  tests/Unit/Accounting/FiscalYearPeriodTest.php \
  tests/Feature/Accounting/FiscalYearBoundaryConsistencyTest.php \
  tests/Feature/Accounting/LegalArchiveFiscalYearBoundaryTest.php \
  tests/Feature/Accounting/AccountingExportFiscalYearBoundaryTest.php
```

Then run formatting, static analysis, and the relevant frontend build:

```bash
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail phpstan analyse --memory-limit=2G
vendor/bin/sail pnpm run build
```

## Long Fiscal-Year Journey

1. Create an explicit fiscal year from `2024-01-01` through `2025-06-30`.
2. Create posted records dated `2024-01-01`, `2024-12-31`, `2025-06-30`, and
   `2025-07-01`.
3. Open the supported accounting report and verify that its period displays
   `2024-01-01` through `2025-06-30`.
4. Generate the accounting export and inspect every CSV/PDF period and row.
5. Generate the legal archive and verify that the first three records are
   included, the July 2025 record is excluded, and repeating generation does
   not duplicate archive rows or replace existing files.
6. Create VAT activity in a reporting period that overlaps the fiscal-year
   boundary. Verify that the complete VAT period is shown, no partial settlement
   is created, and a not-yet-due period does not block closing.
7. Repeat with an overdue unresolved VAT period and verify that the existing
   closing policy blocks closure with an actionable message.
8. Attempt the same requests while the current organization is switched and
   verify that no records or periods cross the tenant boundary.

## Legacy Compatibility Journey

1. Use an organization without an explicit fiscal-year record.
2. Generate the existing 01-01 through 12-31 report and export flow.
3. Verify that the output remains equivalent to the pre-feature behavior.
4. Confirm that existing archive files and old queued export payloads remain
   readable and processable.

## Completion Evidence

Record the focused test output, the long-period boundary counts, the VAT status
outcomes, and the legacy comparison result in the pull request description.