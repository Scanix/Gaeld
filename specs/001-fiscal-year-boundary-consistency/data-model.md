# Data Model: Fiscal-Year Boundary Consistency

## Existing FiscalYear

The existing `FiscalYear` record remains the authoritative persisted accounting
period.

| Field | Meaning | Constraint |
|---|---|---|
| `id` | Organization-scoped fiscal-year identity | UUID, tenant scoped |
| `organization_id` | Owning organization | Required, isolated |
| `name` | User-facing period name | Required |
| `start_date` | Inclusive first date | Before or equal to end date |
| `end_date` | Inclusive last date | Maximum supported duration applies |
| `status` | Planned, operative, expired, or closed | Existing lifecycle |
| `locked_at` | Closing timestamp | Set when closed |
| `locked_by_user_id` | User who closed the period | Nullable until closed |

## Transient FiscalYearPeriod

This is a value object used during request, job, and service execution. It is
not a second persisted fiscal-year model.

| Field | Meaning |
|---|---|
| `organizationId` | Tenant boundary for all consumers |
| `fiscalYearId` | Explicit source record, nullable for legacy fallback |
| `label` | Existing display/export label |
| `fromDate` | Inclusive ISO date |
| `toDate` | Inclusive ISO date |
| `isLegacyFallback` | Whether the period came from the old calendar-year convention |

The object must expose a single inclusive membership rule. Callers must not
reconstruct dates from `label` after resolution.

## LegalArchive Extension

Add a nullable `fiscal_year_id` foreign key to `legal_archives`.

- New explicit-period archives set both `fiscal_year_id` and the existing
  integer `fiscal_year` display label.
- Legacy archive rows remain valid with `fiscal_year_id = null`.
- Backfill only rows that map unambiguously to one existing fiscal-year record.
- Existing `(organization_id, document_type, document_id)` uniqueness remains.
- Existing immutable storage paths are not renamed or rewritten.

## Export Job Input

New export jobs carry the explicit `fiscalYearId` when available and retain the
legacy `fiscalYear` value as a fallback. The job must resolve the period inside
the job process because queued workers do not have the HTTP current-organization
binding.

## VAT Period Status

VAT reporting periods are not converted into fiscal-year entities. The closing
workflow reads their complete legally defined boundaries and exposes one of:

- settled
- not yet due
- overdue and unresolved

No partial VAT settlement record is introduced by this feature.

## State and Integrity Rules

- A closed fiscal year remains closed and immutable.
- A record is in range when its business date is between `fromDate` and
  `toDate`, inclusive.
- An archive operation is idempotent for the existing document identity.
- Organization scope applies to fiscal-year resolution, archive rows, reports,
  and queued export execution.