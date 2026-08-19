# Research: Fiscal-Year Boundary Consistency

**Date**: 2026-08-19

## Decision 1: Use explicit fiscal-year dates as the source of truth

**Decision**: Resolve an explicit `FiscalYear` record to one inclusive
`fromDate` and `toDate` pair and pass that pair to existing consumers.

**Rationale**: The current `FiscalYear` model and `YearEndClosingAction` already
support custom ranges, including Swiss long fiscal years. The divergence occurs
when downstream code converts the period back to an integer calendar year.

**Alternatives considered**:

- Keep passing an integer year: rejected because it cannot represent an
  18-month period.
- Let every consumer resolve dates independently: rejected because it repeats
  the bug and allows reports, archives, and closing to disagree.
- Replace the existing fiscal-year model: rejected because it duplicates a
  working accounting boundary.

## Decision 2: Add one transient period boundary object

**Decision**: Introduce a small immutable `FiscalYearPeriod` DTO containing the
organization ID, optional fiscal-year ID, display label, and inclusive dates.

**Rationale**: The same boundary crosses controllers, queued exports, reports,
archives, PDFs, and closing. A small value object removes accidental mixing of a
calendar-year label with a custom date range without creating a new repository or
domain layer.

**Alternatives considered**:

- Pass three unrelated values everywhere: rejected because it is the current
  source of boundary drift.
- Persist a second fiscal-year entity: rejected as unnecessary duplication.
- Add a general date-range framework: rejected as overengineering for one
  accounting boundary.

## Decision 3: Keep VAT periods independent from financial fiscal years

**Decision**: Do not split or partially settle a VAT reporting period because it
overlaps a financial fiscal year. At closing, evaluate the complete VAT period,
show whether it is settled, not yet due, or overdue, and apply the existing
overdue-settlement policy.

**Rationale**: Swiss VAT filing periods are governed independently from the
organization's annual financial closing. A custom financial year must not
redefine a calendar-based VAT quarter or force a partial VAT declaration.

**Sources reviewed**:

- [Swiss VAT Act, MWSTG](https://www.fedlex.admin.ch/eli/cc/2009/785/de/pdf)
- [ESTV: Paying VAT](https://www.estv.admin.ch/en/paying-vat)
- [ESTV: Annual VAT reconciliation](https://www.estv.admin.ch/en/annual-vat-reconciliation)
- [ESTV: Annual VAT filing guidance](https://www.estv.admin.ch/de/mwst-jaehrliche-abrechnung-2025)

**Alternatives considered**:

- Require settlement of the entire overlapping quarter: rejected because a
  quarter may not yet be due when the financial year closes.
- Settle only the in-range portion: rejected because it creates a partial VAT
  period that is not the legally defined reporting period.
- Redefine VAT periods to match the fiscal year: rejected because it changes the
  tax workflow and is outside this feature.

## Decision 4: Preserve legacy organizations and queued jobs

**Decision**: Prefer `fiscal_year_id` for new requests and jobs, but accept a
validated four-digit year as a compatibility fallback when no explicit record
exists or when an old queued job is processed.

**Rationale**: Existing organizations, links, and queued jobs must continue to
work without rewriting historical data or requiring a flag-based rollout.

## Decision 5: Link new archives to the source fiscal-year record

**Decision**: Add a nullable `fiscal_year_id` to `legal_archives`, retain the
existing integer label, and preserve old rows and storage paths.

**Rationale**: Two non-overlapping periods can share a display year label. The
explicit relationship records provenance for new archives without invalidating
historical files that cannot be mapped safely.

## Decision 6: No new feature flag

**Decision**: Ship the boundary correction as a compatibility fix behind the
existing workflows.

**Rationale**: Running both date-range and calendar-year calculations would
create two sources of truth and prolong the divergence. The legacy fallback is
the compatibility mechanism.