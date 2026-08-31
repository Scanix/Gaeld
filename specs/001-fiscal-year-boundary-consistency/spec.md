# Feature Specification: Fiscal-Year Boundary Consistency

**Feature Branch**: `001-fiscal-year-boundary-consistency`

**Created**: 2026-08-19

**Status**: Draft

**Input**: User confirmation: align all fiscal-year consumers with the
organization's actual fiscal-year boundaries without overengineering or
changing established accounting behavior.

## User Scenarios & Testing

### User Story 1 - Use the selected fiscal year consistently (Priority: P1)

As an organization owner or bookkeeper, I want reports and accounting exports
to use the fiscal year I selected, so that the period shown to me matches the
period used to calculate the data.

**Why this priority**: Financial reports and fiduciary exports are only useful
when their date range is complete and unambiguous. This is the core product
contract for custom and long fiscal years.

**Independent Test**: Given a fiscal year from 2024-01-01 through 2025-06-30,
create posted activity on both boundary dates and outside the range. Generate
the supported reports and export package for that fiscal year. Every included
row and displayed period must cover the selected range exactly, and no outside
row may be included.

**Acceptance Scenarios**:

1. **Given** an organization with a fiscal year from 2024-01-01 through
   2025-06-30, **when** the owner selects that fiscal year for an accounting
   report, **then** the report period is 2024-01-01 through 2025-06-30.
2. **Given** posted journal entries dated 2024-01-01, 2025-06-30, and 2025-07-01,
   **when** the owner generates a fiscal-year export, **then** only the first
   two entries are included.
3. **Given** a fiscal year with no activity, **when** the owner generates a
   report or export, **then** the result is valid, uses the selected dates, and
   contains zero or header-only data as appropriate.
4. **Given** two fiscal years with different date ranges, **when** the owner
   changes the selected year, **then** the report, export label, and included
   records change to the newly selected range without relying on a calendar
   year inferred from the start date.

### User Story 2 - Close and archive the complete fiscal period (Priority: P1)

As an owner responsible for year-end closing, I want closing checks and legal
archives to cover the complete fiscal period, so that documents and VAT work
are not silently omitted from a long fiscal year.

**Why this priority**: Closing and archiving affect legal retention, the
accounting record, and the ability to provide a complete fiduciary handoff.

**Independent Test**: Given a closed fiscal year spanning 18 months, create
invoices, expenses, journal entries, and salary records in both calendar years.
Run the closing and archive workflow and verify that every in-range record is
archived exactly once and that relevant VAT periods are checked.

**Acceptance Scenarios**:

1. **Given** an 18-month fiscal year, **when** the owner archives it, **then**
   every invoice, expense, journal entry, and salary record whose business date
   falls within the inclusive fiscal-year range is archived.
2. **Given** a record dated immediately after the fiscal-year end, **when** the
   owner archives the fiscal year, **then** that record remains unarchived and
   available to the following period.
3. **Given** VAT activity in a reporting period that overlaps the selected
   fiscal year, **when** the owner attempts to close the year, **then** the
   closing workflow evaluates the complete VAT period and does not invent a
   partial-period settlement.
4. **Given** an overlapping VAT period with activity and no settlement,
   **when** the owner attempts to close the year, **then** the existing closing
   policy blocks closure and identifies the unresolved period.
5. **Given** an archive already generated for the selected fiscal year,
   **when** the owner repeats the archive operation, **then** existing immutable
   archive records are not duplicated or overwritten.
6. **Given** PDF and ZIP archive documents are generated, **when** the owner
   downloads them, **then** each document identifies the exact fiscal-year start
   and end dates used to generate its contents.

### User Story 3 - Preserve legacy organization behavior (Priority: P2)

As an existing organization administrator, I want the fiscal-year correction to
preserve my current accounting data and normal 12-month behavior, so that the
change does not require a disruptive migration.

**Why this priority**: Gäld already has organizations using legacy calendar-year
settings and organizations using the newer fiscal-year records. Both must remain
usable during the transition.

**Independent Test**: Given an organization without a custom fiscal-year record,
run the existing reports, export, closing, and archive flows and compare their
period and included records with the current 12-month behavior.

**Acceptance Scenarios**:

1. **Given** an organization using the legacy 01-01 to 12-31 convention,
   **when** the owner runs a supported fiscal-year flow, **then** the result
   remains equivalent to the current calendar-year behavior.
2. **Given** an organization with an explicit fiscal-year record, **when** the
   owner runs the same flow, **then** the explicit record takes precedence over
   the legacy setting.
3. **Given** existing archived documents and exports, **when** the feature is
   deployed, **then** existing immutable files and database records remain
   readable and are not regenerated solely because of this change.

## Edge Cases

- A fiscal year starts or ends on a date containing records at both boundaries.
- A fiscal year crosses two calendar years.
- A fiscal year is longer than 12 months but within the supported Swiss limit.
- A report or export is requested for a fiscal year with no records.
- A record is dated outside the selected fiscal year but has a related payment
  or journal entry inside it.
- A VAT reporting period overlaps a fiscal-year boundary.
- An archive is partially present because a previous PDF or storage operation
  failed.
- A legacy organization has no explicit fiscal-year record.
- Two users request the same archive or export concurrently.

## Requirements

### Functional Requirements

- **FR-001**: The system MUST resolve a selected fiscal year to an explicit
  inclusive start date and end date whenever an explicit fiscal-year record
  exists.
- **FR-002**: Reports, PDFs, ZIP exports, archive generation, and year-end
  closing checks covered by this feature MUST use the resolved fiscal-year date
  range rather than reconstructing a calendar year from an integer label.
- **FR-003**: Date-based records MUST be included when their business date is on
  or after the fiscal-year start and on or before the fiscal-year end.
- **FR-004**: Date-based records outside the resolved range MUST NOT be included
  in the selected fiscal-year report, export, or archive.
- **FR-005**: Generated reports, PDFs, and export metadata MUST display the
  exact start and end dates used for the calculation.
- **FR-006**: Legal archive generation MUST be idempotent and MUST NOT overwrite
  an existing immutable archive solely because the operation is repeated.
- **FR-007**: Year-end closing MUST evaluate every VAT reporting period that
  overlaps the selected fiscal-year range, while preserving the legally defined
  VAT reporting-period boundaries.
- **FR-008**: Year-end closing MUST NOT create or require a partial settlement
  for only the portion of a VAT reporting period that overlaps the fiscal year.
- **FR-009**: A VAT reporting period with activity that overlaps the selected
  fiscal-year range and has no settlement MUST follow the existing closing
  policy and block closure with an actionable period-specific error.
- **FR-010**: The system MUST preserve the existing 01-01 to 12-31 behavior for
  organizations that have no explicit fiscal-year record.
- **FR-011**: Explicit fiscal-year records MUST take precedence over legacy
  calendar-year settings.
- **FR-012**: Existing archive files and database records MUST remain readable
  after deployment and MUST NOT be rewritten as part of a normal read or export.
- **FR-013**: Fiscal-year resolution MUST preserve organization isolation and
  MUST NOT allow a user to select or read another organization's fiscal year.
- **FR-014**: The feature MUST define and test behavior when concurrent requests
  attempt to generate the same fiscal-year archive or export.

## Key Entities

- **Fiscal year**: An organization's named accounting period with an inclusive
  start date, end date, lifecycle status, and closing state.
- **Accounting record**: A dated invoice, expense, journal entry, or salary
  record that may belong to a fiscal period based on its business date.
- **VAT reporting period**: A VAT calculation and settlement period that may
  intersect a fiscal year even when its boundaries are calendar-based.
- **Legal archive**: An immutable retained representation of an accounting
  record or generated fiscal-year document.
- **Accounting export**: A report package containing data and documents for one
  resolved fiscal-year range.

## Success Criteria

### Measurable Outcomes

- **SC-001**: For a test fiscal year spanning 2024-01-01 through 2025-06-30,
  100% of in-range fixture records are included and 100% of out-of-range
  fixture records are excluded across every supported report, export, and
  archive output.
- **SC-002**: Every generated fiscal-year report, PDF, and export package shows
  the same start and end dates used by the underlying data query.
- **SC-003**: Repeating archive generation for the same organization and fiscal
  year creates no duplicate archive rows and does not overwrite an existing
  immutable archive file.
- **SC-004**: Existing legacy calendar-year regression scenarios continue to
  pass without data migration.
- **SC-005**: A year-end closing attempt cannot complete while an applicable VAT
  period with activity remains unsettled under the existing closing policy.

## Accounting Policy Decision

Swiss VAT reporting periods are independent from an organization's annual
financial fiscal year. The fiscal year used for financial statements, archives,
and fiduciary exports must therefore not redefine a VAT quarter or create a
partial VAT settlement. The implementation must evaluate the complete legally
defined VAT period and apply the existing closing policy to its settlement state.
It must not add a new VAT due-date model in this feature.

This decision is based on the Swiss VAT Act and current Federal Tax
Administration guidance:

- [Swiss VAT Act, MWSTG](https://www.fedlex.admin.ch/eli/cc/2009/785/de/pdf)
- [ESTV: Paying VAT](https://www.estv.admin.ch/en/paying-vat)
- [ESTV: Annual VAT reconciliation](https://www.estv.admin.ch/en/annual-vat-reconciliation)

## Assumptions

- A record belongs to a fiscal year according to its business date: invoice
  issue date, expense date, journal-entry date, or salary period date.
- The first implementation covers existing reports, accounting ZIP exports,
  legal archive generation, archive PDFs, and year-end VAT checks identified in
  the baseline audit. It does not redesign VAT law, document dating, or archive
  retention policy.
- Existing archived records are treated as historical output and are not
  automatically rewritten.
- Organizations without explicit fiscal-year records retain the current
  calendar-year fallback.
- VAT reporting periods remain independent from the organization's financial
  fiscal year. The closing workflow evaluates complete overlapping periods and
  applies the existing unsettled-period policy.