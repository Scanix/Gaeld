# Inertia and HTTP Contracts

## Fiscal-Year Selection

Where an explicit fiscal-year record is available, pages use:

```text
fiscal_year_id: UUID
```

Legacy callers may continue to provide:

```text
fiscal_year: YYYY
```

The server resolves `fiscal_year_id` first. A legacy year is accepted only when
the organization has no explicit matching period or when processing an older
queued job.

## Shared Period Payload

Pages that display or generate a fiscal-year report/export receive a period
payload shaped like:

```json
{
  "id": "uuid-or-null",
  "label": "2024",
  "start_date": "2024-01-01",
  "end_date": "2025-06-30",
  "is_legacy_fallback": false
}
```

The displayed dates and the data query must come from the same resolved payload.

## Export Request

`POST /accounting/export` accepts the explicit period identity for new requests
and returns the existing redirect/queued-success behavior. Invalid or
organization-inaccessible IDs return the existing validation or authorization
response rather than silently falling back to another organization.

The completion email and signed download link retain the current behavior but
identify the resolved fiscal-year label and date range.

## Archive Requests

Archive list, generation, PDF, bundle, and lazy-load responses must identify the
resolved period dates. Existing year-based URLs remain readable through legacy
resolution; new generated links carry the explicit period identity where the
route contract permits it.

## Year-End Closing

The closing request continues to accept `fiscal_year_id` and the existing year
fallback. The response must distinguish:

- no VAT activity
- VAT period settled
- VAT period not yet due
- overdue unresolved VAT period blocking closure

## Error Contract

- Invalid period selection: existing validation error semantics.
- Period belonging to another organization: 404/authorization behavior already
  established by tenant-scoped binding and policies.
- Overdue VAT period: existing flash error style with the affected period.
- Empty report/export: valid empty or header-only output with the resolved dates.