# Invoicing Domain

Full invoicing lifecycle: creation, finalization, Swiss QR-bill PDF generation, email delivery, and payment tracking.

## Scope

- **Invoices**: multi-line documents with VAT, discounts, and currency support
- **Invoice Lines**: individual line items with quantity, unit price, and VAT rate
- **Payments**: partial and full payment recording with automatic status transitions
- **Credit Notes**: linked reversal documents
- **Recurring Invoices**: template-based automatic invoice generation
- **Swiss QR Bills**: QR-IBAN payment slips compliant with SIX standards
- **Reminders**: overdue payment notification workflow

## Models

- **Invoice** — Document header with customer, dates, totals, and status lifecycle
- **InvoiceLine** — Line item with description, quantity, unit price, and VAT
- **InvoicePayment** — Payment record with amount and date
- **RecurringInvoice** — Template for scheduled automatic invoice creation

## Integration

- Revenue posting to Accounting domain via `InvoiceAccountingService` → `LedgerService`
- Customer references from Contacts domain
- Invoices are matched during Banking reconciliation
- PDF generation uses TCPDF with Swiss QR-bill via `sprain/swiss-qr-bill`
