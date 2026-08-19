# Divergence Register

## DIV-001: Fiscal-year consumers reduce custom periods to calendar years

**Severity**: P1

**Evidence**: `FiscalYear` and `YearEndClosingAction` support explicit
`start_date` and `end_date`, while archive, PDF, export, and VAT-closing paths
use integer years, `whereYear(...)`, or January 1 to December 31 construction.

**Impact**: A long fiscal year can omit documents, produce incomplete exports,
generate incorrect PDFs, or close without checking all relevant VAT periods.

**Decision**: Make the fiscal-year record and its exact date range the shared
contract for all fiscal-year reports, archives, exports, and closing checks.

**Candidate feature**: `001-fiscal-year-boundary-consistency`

## DIV-002: Draft posting has a weaker period and concurrency contract

**Severity**: P1

**Evidence**: `postEntry()` guards closed fiscal years, but `postDraft()` only
checks balance and posted state. The policy does not check fiscal-year status,
and there is no atomic compare-and-set transition for posting.

**Impact**: A draft may be posted into a closed period, and concurrent requests
may emit duplicate posting events.

**Decision**: Define posting as one atomic transition that revalidates the date,
organization, accounts, balance, and fiscal-year state.

**Candidate feature**: `002-draft-posting-integrity`

## DIV-003: Managed Spec Kit templates do not encode Gäld conventions

**Severity**: P2

**Evidence**: The managed plan and task templates contain generic Python,
mobile, and `src/` examples; the task template treats tests as optional even
though Gäld's constitution requires behavior coverage.

**Impact**: Future generated plans can reference the wrong paths, omit Sail
verification, or under-specify authorization and accounting tests.

**Resolution**: Use project-local overrides under
`.specify/templates/overrides/` and leave managed templates refreshable.

## DIV-004: Frontend contract normalization can hide financial data drift

**Severity**: P2

**Evidence**: `resources/js/lib/inertiaContracts.js` logs a warning and merges
fallback values when a contract fails validation.

**Impact**: Missing or renamed backend props can appear as legitimate zero or
empty states in accounting screens instead of failing a test or request.

**Decision**: Keep development diagnostics, but add explicit contract tests and
decide which financial contracts must fail visibly rather than silently fall
back.

## DIV-005: Historical QA findings need revalidation, not blind carry-over

**Severity**: P2 evidence gap

**Evidence**: Checked-in browser reports target earlier branches and dates.
Several findings are contradicted by current code, including expense VAT
posting, balance-sheet result equity, and issue-date invoice numbering.

**Impact**: Treating old findings as current creates noise; treating them as
resolved without a current run creates false confidence.

**Decision**: Re-run only the affected workflows on current `develop` and mark
each historical finding resolved, reproduced, or stale.