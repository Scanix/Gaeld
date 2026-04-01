# Changelog

All notable changes to Gäld are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [1.17.0] — 2026-03-29

### Added
- **Fiscal year closing** — FiscalYearClosedException prevents posting into closed periods.
- **Opening balances** — GenerateOpeningBalancesAction for new fiscal years.
- **Expense types** — ExpenseType enum for categorisation.

### Improved
- Accounting: year-end closing workflow, lettrage, ledger service.
- All DTOs refined for stricter validation.
- Models: JournalEntry, Invoice, Expense, Organization.
- Search providers: ExpenseSearchProvider, InvoiceSearchProvider.

### Tests
- New: ExpenseTypeTest, FiscalYearClosedExceptionTest.
- Updated: SessionSecurityTest, StripeWebhookSecurityTest, DepreciateAssetActionTest, PostExpenseActionTest.

---

## [1.16.0] — 2026-03-29

### Added
- **Search providers** — BaseSearchProvider contract with domain implementations for contacts, expenses, invoices.
- **Policies** — BudgetPolicy, LegalArchivePolicy, LettrageLotPolicy, ContactPersonPolicy, RecurringInvoicePolicy, SalarySlipPolicy.
- **Request validation** — StoreVatRateRequest, StoreApiTokenRequest, StoreCustomerRequest, StoreSupplierRequest, StoreEmployeeRequest.
- **Payroll actions** — CreateEmployeeAction, GeneratePayrollRunAction, UpdateEmployeeAction.
- **API resources** — ApiTokenResource.
- **Reporting DTOs** — BalanceSheetReport, ProfitAndLossReport, ReportAccountLine.
- **Error page** — generic Vue error handler for 403, 404, 419, 429, 500, 503.

### Improved
- All accounting, invoicing, expenses, banking, and payroll models refined.
- Test suite reorganised by domain.

---

## [1.15.0] — 2026-03-28

### Added
- **VAT rate management** — custom VAT rates per organisation.
- **Security test suite** — 9 security tests covering auth bypass, brute force, IDOR, privilege escalation, webhook SSRF.
- **Activity log** — audit trail with org-scoped visibility.

### Improved
- Permission system expanded to 36 permissions across 5 roles.
- Rate limiting on all auth endpoints.

---

## [1.14.0] — 2026-03-27

### Added
- **Fixed assets** — asset register, depreciation calculations, valuations.
- **Payroll** — employee management, salary slips, Swiss social deductions.
- **Budget management** — annual budgets per account with variance tracking.
- **Recurring invoices** — automatic invoice generation on schedule.

---

## [1.13.0] — 2026-03-26

### Added
- **Bank reconciliation** — CAMT.053 import, smart transaction matching.
- **Payment reminders** — automated reminder emails for overdue invoices.
- **Credit notes** — linked to original invoices with automatic reversal entries.
- **Multi-language** — full support for EN, FR, DE, IT.

---

## [1.12.0] — 2026-03-25

### Added
- **Reports** — profit & loss, balance sheet, cash flow, aging, trial balance, VAT report.
- **Export** — PDF and CSV export for all reports.

---

## [1.11.0] — 2026-03-24

### Added
- **Invoicing** — create, edit, finalise, record payment, PDF generation with Swiss QR-Bill.
- **Expense tracking** — log, categorise, receipt upload with OCR.

---

## [1.10.0] — 2026-03-23

### Added
- **Double-entry accounting** — chart of accounts, journal entries, ledger, trial balance.
- **Swiss chart of accounts** — KMU Kontenrahmen preconfigured.
- **Organisation setup** — setup wizard, onboarding, multi-org switching.
- **Authentication** — email/password, passkeys (WebAuthn), 2FA, email verification.
- **Plugin system** — auto-discovery, service provider-based.
