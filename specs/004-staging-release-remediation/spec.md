# Feature Specification: Staging Release Remediation

**Feature Branch**: `fix/staging-release-remediation`

**Created**: 2026-08-23

**Status**: Draft

**Input**: Correct all defects found while validating CE v3.6.0 and EE v2.7.0 in staging, then prepare CE v3.6 and EE v2.8 without weakening organization isolation or ledger integrity.

## User Scenarios & Testing

### User Story 1 - Use the Community API safely (Priority: P1)

As an organization owner or external integration operator, I want to create API tokens with the permissions and expiration I selected so that authenticated integrations can read and mutate only the authorized organization data.

**Why this priority**: The API is the main CE v3.6 feature and was unusable in staging for both full-access and least-privilege tokens.

**Independent Test**: Create personal and organization tokens with explicit and full access, verify account reads and a permitted journal workflow, verify missing permissions are rejected, verify every supported expiration, then revoke the tokens.

**Acceptance Scenarios**:

1. **Given** a valid organization owner and an `accounts:read` token, **when** the client requests accounts, **then** the API returns only that organization's accounts.
2. **Given** a full-access organization token, **when** the client requests and mutates an allowed resource, **then** the request is authorized without translating between incompatible ability vocabularies.
3. **Given** a token creation request with 7, 30, 90, or 365 days, **when** it is submitted through the web or API, **then** the token is created with the corresponding expiration and no server error.
4. **Given** a token without the required permission, **when** it calls a protected endpoint, **then** the API returns the stable forbidden error without exposing data.

### User Story 2 - Keep accounting calculations exact (Priority: P1)

As a bookkeeper, I want expense postings, payroll previews, payroll slips, cash-flow reports, and VAT results to agree exactly so that I can rely on the ledger and reports for financial decisions.

**Why this priority**: Staging showed unexplained rounding postings and different payroll results between review and posting.

**Independent Test**: Post a VAT-bearing expense, preview and generate payroll for one employee, and compare cash-flow totals against ledger cash movement; all monetary values must reconcile to the cent.

**Acceptance Scenarios**:

1. **Given** a CHF expense of net 120.00 and input VAT 9.72, **when** it is posted, **then** the bank credit is exactly 129.72 and no unexplained revenue correction is created.
2. **Given** a payroll preview for an employee and period, **when** salary slips are generated, **then** gross, deductions, employer charges, and net salary are identical in both representations.
3. **Given** a cash-flow period, **when** the report is generated, **then** operating, investing, financing, beginning, net-change, and ending cash totals reconcile mathematically.
4. **Given** a rounded CHF amount whose difference is a legitimate 5-centime adjustment, **when** it is posted, **then** the adjustment uses the configured rounding account and is never posted to revenue corrections by accident.

### User Story 3 - Use the web workflows without contract or responsive regressions (Priority: P2)

As a CE or EE user, I want reconciliation, settings, and shared frontend components to render within their contracts and viewport so that normal workflows remain usable on desktop and mobile.

**Why this priority**: These defects do not always block the server, but they hide state and make important workflows unreliable.

**Independent Test**: Load reconciliation with empty and populated data, inspect the Inertia contract, exercise masked fields, and load Settings at 375px, 768px, and 1440px without horizontal overflow or console warnings from the touched components.

**Acceptance Scenarios**:

1. **Given** a valid reconciliation response, **when** the page normalizes its props, **then** no contract mismatch warning is emitted and all expected fields remain available.
2. **Given** a 375px viewport, **when** Settings is opened, **then** all settings sections remain discoverable without horizontal page overflow.
3. **Given** a masked field, **when** it is rendered and edited, **then** the component exposes the required mask API without a browser console warning.
4. **Given** the CE build, **when** the frontend is bundled, **then** no EE-only page or component source is included.

### User Story 4 - Publish coordinated CE and EE releases (Priority: P2)

As an operator, I want CE and EE release metadata, tests, and deployment references to identify compatible immutable versions so that staging can be promoted safely and production can be deployed later without mixing private code into CE.

**Why this priority**: The release must be repeatable after remediation and must preserve the public/private repository boundary.

**Independent Test**: Build CE without EE sources, run CE checks, run EE checks in its private repository, verify CE `v3.6.0` and EE `v2.8.0` metadata, and verify staging deployment instructions pin both tags.

**Acceptance Scenarios**:

1. **Given** the corrected CE branch, **when** release checks run, **then** tests, static analysis, formatting, contract validation, and frontend build pass.
2. **Given** the EE plugin release, **when** its manifest and private changelog are reviewed, **then** they identify EE `v2.8.0` and do not enter the CE repository.
3. **Given** staging deployment, **when** the operator supplies immutable CE and EE refs, **then** only the staging target is deployed and production remains unchanged.

## Edge Cases

- Existing tokens created with legacy colon-style abilities remain usable during migration, or fail with a stable authorization response rather than silently broadening access.
- Empty abilities continue to mean full access only where the existing contract explicitly defines that behavior.
- Expiration input arrives as an HTTP string and must be converted to a numeric duration before date arithmetic.
- CHF amounts exactly on a 5-centime boundary do not create a rounding line.
- Cash-flow periods with no transactions report zero consistently.
- Payroll employees without custom deduction rates use the documented defaults consistently in preview and generation.
- Empty reconciliation payloads normalize without warnings.
- Settings tabs fit or scroll accessibly on narrow viewports.
- CE builds and tests run with EE disabled; EE builds and tests run from the private plugin repository.

## Requirements

### Functional Requirements

- **FR-001**: The API MUST expose one canonical permission vocabulary across token settings, token validation, permission mapping, API metadata, and documentation.
- **FR-002**: The API MUST authorize personal and organization tokens according to their effective abilities and organization scope.
- **FR-003**: Token expiration durations MUST be handled numerically for all supported values and MUST never produce a 500 for valid input.
- **FR-004**: Expense ledger entries MUST balance using exact decimal arithmetic and MUST not create unexplained residual revenue postings.
- **FR-005**: Payroll preview and generated salary slips MUST use the same deduction calculation and persist identical employee/net values.
- **FR-006**: Cash-flow report subtotals MUST reconcile to net cash change and ending cash for every selected period.
- **FR-007**: Reconciliation frontend props MUST conform to the declared normalization contract without avoidable warnings.
- **FR-008**: Settings MUST remain usable at mobile, tablet, and desktop widths without page-level horizontal overflow.
- **FR-009**: The CE/EE boundary MUST remain enforced in source trees, builds, tests, release metadata, and deployment references.
- **FR-010**: CE v3.6.0 corrective release notes and EE v2.8.0 private release metadata MUST identify the same validated staging compatibility pair.
- **FR-011**: Every corrected behavior MUST have focused automated coverage, and the full CE checks MUST pass before staging redeployment.

## Key Entities

- **API Token**: A personal or organization-scoped bearer credential with abilities and optional expiration.
- **Expense Journal Entry**: The balanced ledger representation of net expense, input VAT, and payment account movement.
- **Salary Slip**: The persisted payroll result derived from an employee, period, gross salary, and deduction rules.
- **Cash-Flow Report**: A read-only period summary whose activities must reconcile to ledger cash movement.
- **CE/EE Release Pair**: An immutable public CE tag and private EE tag deployed together in staging or production.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% of supported token expiration values create tokens successfully in automated and staging checks.
- **SC-002**: 100% of tested API token types authorize permitted account reads and reject forbidden operations with stable errors.
- **SC-003**: 100% of corrected expense and payroll examples reconcile to the cent between UI, ledger, and reports.
- **SC-004**: The primary CE pages have zero page-level horizontal overflow at 375px, 768px, and 1440px, including Settings.
- **SC-005**: The focused regression suite and full CE suite pass with no new static-analysis or contract diagnostics.
- **SC-006**: Staging can identify the active CE and EE immutable refs, and no production ref is changed by the staging release process.

## Assumptions

- The existing Laravel, Inertia, Vue, Sanctum, PostgreSQL, and plugin architecture remains in place.
- Existing staging data may be reset by the environment, but the QA account and its test records are disposable.
- EE v2.8.0 is a private plugin release and is never copied into or committed to the public CE repository.
- Existing business rules and Swiss rounding policy remain authoritative; the remediation corrects inconsistencies rather than changing accounting policy.
