# Changelog

All notable changes to Gäld are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [2.18.0] — 2026-04-14

### Added
- **Security: invitation email guard** — `InvitationController::accept` and `InvitationService::accept` now verify the authenticated user's email matches the invitation before proceeding (defense-in-depth against cross-account invitation acceptance).
- **Security: invoice lifecycle policies** — `InvoicePolicy` now has dedicated `duplicate` and `creditNote` gates (previously used the generic `view` gate), and `recordPayment` is restricted to `Sent`/`Overdue` invoices only.
- **Security: cross-org IDOR prevention** — `BasePolicy::belongsToOrganization` now validates against the bound `CurrentOrganization` when available, preventing access to resources from non-active organizations.
- **Security: VAT server-side enforcement** — `CreateExpenseAction` and `UpdateExpenseAction` now compute VAT amount server-side from the VAT rate record; client-supplied VAT values are ignored to prevent financial manipulation.
- **Security: expense account type validation** — `expense_account_code` validation now restricts to accounts of type `Expense` only.
- **Security: invoice duplicate/creditNote authorization** — `InvoiceLifecycleController` now uses `duplicate` and `creditNote` policy gates instead of the generic `view` gate.
- **Sentry Vite plugin** — source maps are uploaded to Sentry on EE production builds; `SENTRY_AUTH_TOKEN`, `SENTRY_ORG`, `SENTRY_PROJECT` added to `.env.production.example`.
- **CSP nonce** — `AddSecurityHeaders` generates a per-request CSP nonce and replaces `unsafe-inline` with `nonce-{nonce}` in the `Content-Security-Policy` header.
- **GDPR retention** — `UserService::deleteAccount` preserves organizations with posted journal entries (Swiss OR Art. 958f), anonymizing user PII instead of hard-deleting.
- **AHV encryption migration** — new migration to encrypt employee AHV numbers at rest.
- **i18n** — `invitation_wrong_account` key added across DE, EN, FR, IT.

### Changed
- **Throttle in dev/test** — rate limiting is now disabled only when `DISABLE_THROTTLE=true` is set (opt-in), not unconditionally in `testing` environment.
- **`is_saas_admin` lazy evaluation** — Inertia shared `is_saas_admin` prop is now a closure evaluated only on `saas-admin/*` routes.
- **CAMT XML parsing** — hardened with additional validation.

### Moved (EE)
- **Sentry Laravel** — `sentry/sentry-laravel` moved from core `composer.json` to the `gaeld-ee` plugin; Sentry service provider is booted conditionally by `GaeldEEServiceProvider` when `SENTRY_LARAVEL_DSN` is set.

### Chore
- **Dependencies** — all Composer and pnpm packages updated to latest minor/patch versions across `api`, `web`, `docs`, `dl-stockaj`.
- **CE isolation** — removed `gaeld-ee` namespace references from `Organization.php`, `phpstan.neon`, `contract/app-contract.json`, and route comments; `WithActiveSubscription` test trait uses dynamic class resolution.

---

## [2.12.0] — 2026-04-12

### Added
- **Organization management** — Create page (`Organizations/Create.vue`), delete action with session cleanup, chart-of-accounts seeding on creation.
- **Dashboard smart year** — `resolveDisplayYear()` falls back to the most recent year with posted entries instead of always using the current year.
- **Receipt preview** — inline image/PDF preview modal on Expense Show and Invoice Show pages with download link.
- **OCR pending widget** — dashboard widget showing pending OCR scans count with link to expenses.
- **Ledger query** — `latestPostedEntryDate()` method on LedgerQueryService.

### Fixed
- **Lang files** — missing commas causing PHP parse errors in all 4 locales.
- **Dashboard year** — chart tooltips, transaction filtering and chart description now use the resolved display year.

### Removed
- **WebAuthn legacy controllers** — deleted unused `WebAuthnLoginController` and `WebAuthnRegisterController`; removed stale `loginOptions()` from PasskeyController.

### Improved
- **Tests** — updated ScanReceiptTest, OrganizationCrudFlowTest, ReportingFlowTest, BruteForceProtectionTest, DashboardServiceTest.
- **i18n** — OCR widget, receipt preview, and organization CRUD keys added across DE, EN, FR, IT.
- **PHPStan** — baseline cleaned up (removed stale PasskeyController entry).

---

## [2.11.0] — 2026-04-12

### Added
- **Expense notifications** — ExpenseSubmittedNotification (to approvers), ExpenseApprovedNotification (to submitter), InvoicePaymentRecordedNotification (to org users with invoice permissions).
- **OCR → Expense pre-fill** — successful OCR scans link directly to Expense Create with pre-filled data (amount, date, vendor, VAT, receipt).
- **Notifications full page** — `/notifications/all` Inertia page with pagination, mark-all-read, and "View all" link in NotificationBell dropdown.
- **Expense user tracking** — `user_id` column on expenses table to track the submitter.

### Improved
- **i18n** — expense/invoice notification keys added across DE, EN, FR, IT.

---

## [2.10.0] — 2026-04-12

### Added
- **Device session tracking** — DeviceSession model with user-agent parsing, DeviceSessionController (list, revoke single, revoke all others), active sessions UI on Profile page.
- **Passkey as 2FA** — passkey can be used as a second factor alongside password; multi-method chooser on TwoFactorChallenge page (TOTP, passkey, recovery code); passwordless login removed.
- **Cross-domain auth cookie** — `gaeld_auth` cookie set on login/2FA success (domain `.gaeld.ch`) to enable landing page redirect for authenticated users.
- **Notification links** — clickable URLs in notification bell items.

### Improved
- **i18n** — active sessions and 2FA method chooser keys added across DE, EN, FR, IT.
- **PHPStan** — baseline cleaned up (removed stale entries for refactored TwoFactorChallengeController).

---

## [2.9.0] — 2026-04-11

### Added
- **In-app notifications** — notification bell in Topbar, NotificationController, preferences per user, Horizon queue config.
- **Receipt scan tracking** — ReceiptScan model with ReceiptScanStatus enum, NullOcrService fallback, scan result persistence.
- **Year-end closing service** — ClosingAccountsService extracts closing logic from controller, new FiscalYearCoherenceTest.
- **Dashboard refactor** — DashboardService simplified, layout persistence removed (drop_dashboard_layout migration).
- **Payroll 2026** — Swiss social-deduction rates extended for 2026 in SwissDeductionService.
- **OCR improvements** — TesseractOcrService hardened with better text parsing; QuickReceiptButton/Modal upgraded.
- **Chart of accounts** — new AccountCode constants, ChartTemplateService improvements, seeder additions.

### Fixed
- **Invoicing** — harden recurring-invoice generation job and invoice number sequencing edge cases.
- **Multi-currency** — correct exchange-rate cache key collision.

### Improved
- **i18n** — notification-related keys added across DE, EN, FR, IT.
- **PHPStan** — baseline regenerated (reduced from ~800 to 532 errors).
- **Support traits** — strict-type declarations on MapsToSnakeCase, OmitsNullValues, Auditable.

---

## [2.8.0] — 2026-04-10

### Added
- **PWA** — service worker (`sw.js`), OfflineBanner component, updated `site.webmanifest`.
- **UI components** — Alert, Banner, FileUpload (replaces FileUploadDropzone + FormFileInput), PageHeader, SharePrintButton, StatCard.
- **Full i18n translation files** — `de.json`, `en.json`, `fr.json`, `it.json` + `actions.php`, `auth.php`, `http-statuses.php`, `pagination.php`, `passwords.php` per locale.
- **Security headers** — updated AddSecurityHeaders middleware, CookieConsent improvements.

### Fixed
- **deploy.php** — untracked on develop/main (production-only); Deployer `cd` to release_path before `nvm use`; build Vite assets on server.
- **Cookie consent** — bake `VITE_COOKIE_DOMAIN=.gaeld.ch` into Vite production build.
- **CORS** — add `docs.gaeld.ch` to allowed origins.
- **Bexio import** — map `Kontaktname` CSV header in BexioParser contact import.

### Improved
- Pages updated: Dashboard, Banking, Expenses, Invoices, Migration, Organizations, Payroll, Reports, Settings, Users/Profile, Assets, Auth.

---

## [2.7.0] — 2026-04-09

### Added
- **Bexio XLSX import** — support for Bexio `.xlsx` exports (addresses, invoices, bills, expenses).

### Fixed
- **CSP** — add `docs.gaeld.ch` to `frame-src` Content Security Policy directive.
- **Horizon** — CSP header fix, heartbeat config, system message banner, SaaS admin Horizon link.
- **Deploy** — replace `gaeld-worker` with `gaeld-horizon` restart; add `CI=true` for pnpm install; use pnpm in assets:build.

### CI/CD
- Pre-push hook enforcing Pint + PHPStan before pushing to production.
- CI pipeline switched to production branch only.
- Keep-ours merge driver for production-only files.
- Coverage threshold lowered to 65% (to raise incrementally).
- Removed hardcoded `APP_KEY` from CI config.

---

## [2.6.0] — 2026-04-09

### Added
- **FormFileInput.vue** — reusable file upload component with label, error, and slot support.
- **Setup Wizard stepper** — 3-step wizard (Account → Organisation → Settings) with visual progress indicator.
- **Sidebar sub-categories** — Accounting menu grouped under Core, Tax & VAT, Reports & Archives, Period, Advanced headings.
- **Collapsible sidebar sections** — expand/collapse with chevron toggle, localStorage persistence.
- **Global search quick navigation** — ⌘K shows page quick links when empty; navigation results mixed into search.
- **Form section headings** — all Create/Edit forms use `<h3>` + `<hr>` section dividers.
- **Tooltip help** — contextual tooltips on internal notes, AHV number, IBAN fields.
- **Telegram alerts for Horizon** — long-wait queue events now trigger a Telegram notification instead of Slack.

### Improved
- **Mobile responsiveness** — action headers use `flex-wrap` + icon-only buttons on mobile across all Show/Create/Edit pages.
- **Address field ordering** — Customer/Supplier forms reorder to Address → Postal → City → Country.
- **i18n** — ~30 new keys across EN, FR, DE, IT.
- **Security headers** — added `Permissions-Policy`, upgraded HSTS `max-age` to 1 year, fixed autocomplete on sensitive fields.

### Fixed
- **Scheduler** — `MonthlyDepreciationJob` was running at 00:00 on the 1st; now correctly fires at 05:00 (`monthlyOn(1, '05:00')`).
- **Scheduler** — removed redundant `backup:run` / `backup:clean` app-level commands; DB and file backups are handled by system scripts with OneDrive sync via rclone.

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
