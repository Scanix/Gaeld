# Expenses Domain

Expense tracking with approval workflow, OCR receipt scanning, and ledger posting.

## Scope

- **Expenses**: vendor bills with amount, category, VAT, and approval status
- **Categories**: configurable expense classification
- **Approval Flow**: draft → pending → approved → posted lifecycle
- **OCR**: Tesseract-based receipt text extraction for auto-fill

## Models

- **Expense** — Vendor bill with amount, date, category, VAT rate, and status
- **ExpenseCategory** — Organization-level expense classification

## Integration

- Posts approved expenses to the Accounting domain via `LedgerService`
- Expenses are matched during Banking reconciliation
- Vendors reference the Contacts domain's supplier records
