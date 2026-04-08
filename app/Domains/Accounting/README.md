# Accounting Domain

Core financial engine: chart of accounts, double-entry ledger, VAT reporting, and year-end closing.

## Scope

- **Chart of Accounts**: Swiss SME chart templates, account CRUD, and type classification
- **Journal Entries**: double-entry transactions with debit/credit lines and optional lettrage
- **VAT**: rate management, VAT entry tracking, settlement posting, and quarterly reports
- **Year-End Closing**: revenue/expense zeroing, opening balance generation, legal archiving
- **Social Charges**: Swiss AHV/IV/EO calculation for self-employed income

## Models

- **Account** — Ledger account with type (asset, liability, revenue, expense, equity)
- **JournalEntry** — Double-entry transaction header with type classification
- **TransactionLine** — Individual debit or credit line within a journal entry
- **VatRate** — Tax rate definitions (standard, reduced, special)
- **VatEntry** — Per-line VAT tracking for reporting
- **Budget** — Annual budget allocations per account
- **LettrageLot** — Groups matched transaction lines for reconciliation
- **LegalArchive** — Immutable year-end snapshots for Swiss compliance

## Integration

- Central domain consumed by Banking (reconciliation entries), Invoicing (revenue posting), Expenses (expense posting), Payroll (salary entries), and Assets (depreciation entries)
- `LedgerService` is the primary entry point for other domains to post journal entries
