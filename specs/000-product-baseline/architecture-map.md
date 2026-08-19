# Gäld Architecture Map

## Ownership Boundaries

### Accounting

Owns chart of accounts, journal entries, fiscal years, VAT records, closing,
opening balances, and legal archives. Other domains must use accounting
services for ledger mutations.

### Invoicing

Owns invoices, lines, payments, QR-Bill data, credit notes, and recurring
invoices. `InvoiceAccountingService` translates invoice events into ledger
entries through `LedgerService`.

### Expenses

Owns expense capture, approval, receipts, recurring expenses, and expense
posting. `ExpenseService` builds the expense ledger entry and VAT entry.

### Banking

Owns bank accounts, imported bank transactions, matching, reconciliation, and
payment initiation. Reconciliation services create accounting entries through
the accounting boundary.

### Reporting

Owns read-only projections such as P&L, balance sheet, cash flow, aging, and
dashboard data. It must not mutate the ledger.

### Organizations and Users

Own organization membership, current-organization context, roles, permissions,
feature flags, onboarding, and SaaS access.

## Backend Request Flow

```text
Route
  -> authentication / verification / organization middleware
  -> Form Request validation and authorization
  -> Policy or Gate authorization
  -> Controller orchestration
  -> Action or domain Service
  -> Eloquent models / transactions / domain events
```

The controller should not become a second owner of accounting rules. A new
write operation with financial invariants belongs in an existing Action or
Service, or in a narrowly justified new one.

## Frontend Request Flow

```text
Inertia route response
  -> Page component under resources/js/Pages
  -> shared UI and composables under resources/js/Components and lib
  -> Inertia Form, useForm, router, or useHttp
  -> server-side validation and authorization
```

Frontend calculations may improve feedback, but server-side accounting values
and permissions remain authoritative. Loading, empty, error, forbidden, and
archived states are part of the page contract.

## Non-Negotiable Invariants

- Debits and credits balance for every posted journal entry.
- Posted entries cannot be edited in place.
- Closed fiscal years reject new ledger postings.
- Organization-scoped data cannot cross tenant boundaries.
- Reporting reads do not write to the ledger.
- VAT records and their accounting treatment agree.
- Archived records remain immutable and retrievable for the retention period.

## Architecture Guardrails

- Reuse Laravel and existing domain patterns before adding abstractions.
- Keep organization scoping in `BelongsToOrganization`, explicit raw queries,
  and Form Request rules where each mechanism is required.
- Keep tests close to the behavior they prove and run them through Sail.
- Treat frontend/backend contract normalization as a diagnostic aid, not as a
  substitute for fixing a contract mismatch.
- Do not extract CRUD into Actions without side effects or invariants.