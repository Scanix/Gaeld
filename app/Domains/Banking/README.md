# Banking Domain

Bank account management, transaction import (CAMT/CSV), and smart reconciliation engine.

## Scope

- **Bank Accounts**: IBAN-linked accounts synced with the chart of accounts
- **Transaction Import**: CAMT.053/054 XML and CSV parsing with duplicate detection
- **Reconciliation**: multi-strategy matching (invoice, expense, contra-account, personal patterns)
- **Suggestions**: scored candidate ranking for manual reconciliation review

## Models

- **BankAccount** — Organization bank account linked to a ledger account
- **BankImport** — Import session tracking (file, format, status)
- **BankTransaction** — Individual transaction with creditor/debtor info and reconciliation state
- **BankMatch** — Stored match candidate linking a transaction to an invoice
- **PersonalTransactionPattern** — User-defined rules for recurring transaction categorization

## Services

- **ReconciliationService** — Orchestrates multi-strategy matching (invoice, expense, contra-account)
- **MatchingService** — Invoice candidate scoring by amount, reference, and date proximity
- **SuggestionService** — Generates ranked reconciliation suggestions for UI display
- **RuleEngineService** — Applies personal patterns for auto-categorization
- **BankImportService** — Coordinates file parsing, dedup, and transaction creation

## Integration

- Consumes Accounting domain's `LedgerService` for posting reconciliation journal entries
- References Invoicing invoices and Expenses expenses for matching
- CAMT/CSV parsers are isolated in `Parsers/` subdirectory
