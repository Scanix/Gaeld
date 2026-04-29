# Manual Smoke Checklist (Demo)

This guide validates real workflows in the UI using demo data plus sample fixture files.

## Prerequisites

1. Run setup with demo data:

```bash
./gaeld setup --demo
./vendor/bin/sail artisan optimize:clear
```

2. Sign in with the credentials printed by setup.

3. Keep these fixture files open:
- tests/fixtures/manual-smoke/fake-invoices.csv
- tests/fixtures/manual-smoke/fake-expenses.csv
- tests/fixtures/camt053_sample.xml
- tests/fixtures/camt053_qr_sample.xml
- tests/fixtures/camt054_sample.xml

## Scenario 1: Invoice posting and trial balance

1. Create invoice INV-SMOKE-001 from fake-invoices.csv.
2. Finalize the invoice.
3. Record payment (Bank account CHF, amount 540.50).
4. Open Trial Balance as of 2026-04-17.

Expected:
- Account 3000 has credit increase of 500.00.
- Account 2200 has credit increase of 40.50.
- Account 1020 has debit increase of 540.50.
- Account 1100 is not increased by net effect after full payment.

## Scenario 2: Open receivable remains visible

1. Create and finalize INV-SMOKE-002.
2. Do not record payment.
3. Reopen Trial Balance.

Expected:
- Account 1100 (Accounts Receivable) shows open debit 1081.00 for this invoice.
- 3000 and 2200 still increase from posting.

## Scenario 3: Partial payment behavior

1. Create and finalize INV-SMOKE-003.
2. Record a partial payment of 500.00.
3. Open invoice details.

Expected:
- Invoice status stays sent/overdue (not paid) until fully settled.
- Remaining due = 797.20.
- Ledger includes payment entry 1020 debit / 1100 credit for 500.00.

## Scenario 4: Expense workflow

1. Create expenses from fake-expenses.csv.
2. Approve and post one pending/approved expense.
3. Open Journal Entries.

Expected:
- Posted expenses generate balanced entries.
- Expense account is debited, bank account (1020) credited.
- Pending and approved expenses are not posted until action is executed.

## Scenario 5: Bank reconciliation import

1. Go to Reconciliation and import tests/fixtures/camt053_sample.xml.
2. Import tests/fixtures/camt053_qr_sample.xml.
3. Optional: import tests/fixtures/camt054_sample.xml.
4. Confirm one suggested match.

Expected:
- Transactions are created without duplication on re-import.
- Suggested matches appear for matching invoice amounts/references.
- Confirmed match records payment and marks transaction as reconciled.

## Quick API/feature sanity check

```bash
./vendor/bin/sail artisan test tests/Feature/Invoicing/InvoiceFlowTest.php
./vendor/bin/sail artisan test tests/Feature/Banking/ReconciliationFlowTest.php
./vendor/bin/sail artisan test tests/Feature/Accounting/VatReportTest.php
./vendor/bin/sail artisan test tests/Feature/Reporting/CashFlowReportTest.php
```

## Notes

- If you see unexpected 419 responses after setup, clear cache again with optimize:clear.
- Avoid running multiple heavy test suites in parallel on the same DB to reduce deadlock risk.
