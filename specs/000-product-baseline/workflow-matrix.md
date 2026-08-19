# Product Workflow Matrix

Status meanings:

- **Evidence present**: relevant automated tests or code evidence exist.
- **Runtime unverified**: current behavior was not re-run during this audit.
- **Divergence candidate**: evidence shows competing behavior or an important
  path is not covered.

| Workflow | Current surfaces | Existing proof | Baseline status |
|---|---|---|---|
| Setup and onboarding | `Organizations`, `Users`, onboarding pages and controllers | `OnboardingWizardTest`, `OrganizationSetupFlowTest`, `SetupModeTest` | Evidence present; runtime unverified |
| Authentication and tenant access | auth controllers, organization middleware, policies | `AuthenticationTest`, `TenantIsolationTest`, `PolicySecurityTest` | Evidence present; runtime unverified |
| Invoices and payments | `Invoicing`, invoice lifecycle, accounting service | `InvoiceFlowTest`, `CreditNoteFlowTest`, `PaymentReminderFlowTest`, API tests | Evidence present; runtime unverified |
| Expenses and VAT | `Expenses`, approval workflow, `ExpenseService` | `ExpenseFlowTest`, receipt tests, accounting coherence tests | Evidence present; runtime unverified |
| Banking and reconciliation | `Banking`, CAMT/MT940 import, matching services | `ReconciliationFlowTest`, personal/reconciliation tests | Evidence present; runtime unverified |
| Ledger and journal entries | `LedgerService`, journal controllers and policies | `LedgerInvariantsTest`, `ManualJournalEntryTest`, HTTP flow tests | Evidence present; draft-after-close case missing |
| VAT reporting and settlement | `VatReportService`, VAT controllers and settlement action | `VatReportTest`, fiscal coherence tests | Evidence present; calendar-period divergence candidate |
| Reports and dashboard | `ReportingService`, report controllers, dashboard page | reporting, dashboard, aging, and export tests | Evidence present; long fiscal-year coverage incomplete |
| Fiscal years and closing | `FiscalYearService`, `YearEndClosingAction`, closing wizard | fiscal-year and year-end test groups | Evidence present; custom-range consumers incomplete |
| Legal archives and exports | `LegalArchivingService`, PDF action, accounting export job | archive, PDF, and export tests | Evidence present; long fiscal-year coverage incomplete |
| Payroll and assets | `Payroll`, `Assets`, accounting integrations | payroll and asset flow tests | Evidence present; runtime unverified |
| Migration and paper opening balances | `Migration`, opening-balance actions and wizard | migration and opening-balance tests | Evidence present; runtime unverified |
| SaaS and EE access | feature flags, subscription contract, EE integration | billing and feature-flag tests; historical SaaS reports | Historical evidence; current runtime unverified |

## Evidence Gap

The next validation pass should run the highest-risk workflows against the
current `develop` build after Docker is available. The first scenario should be
an 18-month fiscal year because it discriminates between true fiscal-year logic
and calendar-year assumptions.