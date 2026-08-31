# Implementation Plan: Staging Release Remediation

**Branch**: `fix/staging-release-remediation` | **Date**: 2026-08-23 | **Spec**: [spec.md](spec.md)

## Summary

Correct the defects found during the CE v3.6.0 and EE v2.7.0 staging
validation. The CE owns the API token contract, ledger/reporting fixes, and
shared web UI fixes. The private EE repository owns the v2.8.0 manifest and
release metadata; no proprietary source enters the CE repository.

## Technical Context

**Runtime**: PHP 8.4, Laravel 13, PostgreSQL 16, Sanctum, Inertia v3, Vue 3,
Vite, pnpm.

**Architecture**: Controllers orchestrate. Expense, payroll, accounting, and
reporting services own business rules. All ledger writes continue through
`LedgerService`. Token organization scope continues through
`EnsureApiOrganization`, the custom Sanctum token model, and policies.

**Testing**: PHPUnit through Sail, Pint, PHPStan, and the CE frontend build.
Browser validation will redeploy staging only after the focused and full CE
checks pass. EE tests and its asset build run from the private plugin checkout.

## Root-Cause Decisions

1. **Canonical token abilities**: use the dotted `Permission` enum values already
   used by policies and `TokenPermissionMap` (`accounting.view`,
   `invoicing.create`, etc.). Web settings and API requests will derive their
   list from the same map. A compatibility normalization layer will accept the
   old colon names only when they map unambiguously to a canonical permission;
   it will never broaden an ability.
2. **Token expiry**: cast validated HTTP input to `int` before Carbon date
   arithmetic in both web and API token controllers.
3. **Expense rounding**: preserve Swiss 0.05 rounding only when the gross
   amount is actually paid/settled in cash. The configured rounding account is
   `3900`; a residual may not silently alter the bank amount and revenue report.
   Tests will define whether the existing business rule should round the gross
   total or preserve exact ledger amounts for bookkeeping entries.
4. **Payroll consistency**: preview and generation will call the same
   `PayrollCalculator` result and persist/render that result without a second
   deduction calculation.
5. **Cash flow**: calculate the indirect statement so activity totals reconcile
   to actual cash movement; an explicit reconciliation line belongs in the
   operating section and must be included in every returned subtotal.
6. **Inertia contract**: align the reconciliation schema with the actual
   serialized paginator/model payload and keep normalization warning-free for
   valid empty and populated payloads.
7. **Responsive UI**: make Settings tabs horizontally navigable within the
   viewport at 375px while retaining keyboard focus visibility.
8. **EE release**: update private plugin metadata to v2.8.0 only after the CE
   fixes are validated; deploy staging with the immutable pair.

## Affected Surfaces

### CE API

- `app/Domains/Api/Controllers/TokenSettingsController.php`
- `app/Domains/Api/Controllers/ApiTokenController.php`
- `app/Domains/Api/Controllers/OrgTokenController.php`
- `app/Domains/Api/Requests/StorePersonalTokenSettingsRequest.php`
- `app/Domains/Api/Requests/StoreApiTokenRequest.php`
- `app/Http/Middleware/Api/TokenPermissionMap.php`
- `app/Http/Middleware/EnsureApiOrganization.php`
- `tests/Feature/Api/ApiTokenManagementTest.php`
- `tests/Feature/Api/ApiContractTest.php`
- `tests/Security/Api/ApiTokenSecurityTest.php`

### CE accounting and payroll

- `app/Domains/Expenses/Services/ExpenseService.php`
- `app/Support/SwissRounding.php` only if a helper defect is proven
- `app/Domains/Payroll/Services/PayrollCalculator.php`
- `app/Domains/Payroll/Actions/GeneratePayrollRunAction.php`
- `app/Domains/Payroll/Controllers/PayrollRunController.php`
- `app/Domains/Reporting/Services/ReportingService.php`
- focused Expense, Payroll, and Cash Flow tests

### CE frontend

- `resources/js/Pages/Settings/ApiTokens.vue`
- `resources/js/Pages/Settings.vue` equivalent settings page location if found
- `resources/js/lib/inertiaContracts.js`
- `resources/js/Pages/Banking/ReconciliationShow.vue`
- focused frontend contract/build checks

### EE private release

- `plugins/gaeld-ee/plugin.json`
- private EE changelog/release metadata if present
- private EE tests and asset build

## Data and Compatibility

No new migration is planned. Existing tokens remain readable. Existing
colon-style abilities are normalized at authorization time or during display
only through a fixed compatibility map; unknown legacy abilities remain
restricted. Existing journal, expense, payroll, and report records are not
rewritten automatically.

The fixes must not add EE files to CE. CE builds run with plugin pages omitted;
EE builds continue to discover plugin frontend pages from `plugin.json`.

## Verification Plan

1. Add failing focused tests for token permissions/expiry, expense gross
   posting, payroll preview/generation parity, and cash-flow reconciliation.
2. Implement each owning service/controller fix and run its focused tests.
3. Run Pint and full PHPStan after PHP changes.
4. Run the CE frontend build and contract checks after Vue changes.
5. Run the full CE suite through Sail.
6. Run private EE tests/build and update EE release metadata to v2.8.0.
7. Redeploy staging with CE v3.6.0 corrective candidate and EE v2.8.0.
8. Repeat the staging smoke paths, then execute the remaining multi-user,
   archive, and fiscal-year scenarios only after financial/API gates are green.

## Rollout and Rollback

Staging is the first target. Production remains untouched until the user
reviews the staging report and manually merges the production MR. Rollback uses
the previous CE tag and the tested EE `v2.7.0` ref. API access can be disabled
with `FEATURE_API_ACCESS=false` while preserving existing web accounting.

## Constitution Check

- [x] Accounting writes remain behind `LedgerService`.
- [x] Organization and token isolation remain server-enforced.
- [x] CE and EE ownership is explicit and private source is excluded from CE.
- [x] Each behavior change receives focused automated coverage.
- [x] No dependency or schema change is planned without evidence.
- [x] Staging deployment is separate from production and reversible.
