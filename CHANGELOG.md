# Changelog

All notable changes to Gäld are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

---

## [3.4.1] — 2026-05-17

### Changed
- **Dashboard: removed Getting Started checklist** — the onboarding
  checklist panel has been removed from the dashboard; it was more
  distracting than useful and will be replaced by a proper onboarding
  wizard in a future release.

### Fixed
- **EE: subscription plan gating now enforced in SaaS mode** — EE features
  (bank_sync, auto_reconciliation, automation, multi_currency, api_access,
  rule_engine, advanced_permissions, and others) are now always gated by
  the organisation's subscription plan when `FEATURE_SAAS=true`. Previously
  a server-wide flag such as `FEATURE_BANK_SYNC=true` bypassed the per-org
  plan check, granting every organisation free access to paid features.

---

## [3.4.0] — 2026-05-17

### Added
- **Accounting: fiscal year management (#17)** — first-class fiscal year
  entity with planned / operative / expired / closed lifecycle, overlap
  guard, and support for long fiscal years (up to Swiss legal maximum,
  e.g. company founding). New `FiscalYearService`, REST + Inertia UI,
  migrations with backfill from existing organisation settings.
- **Accounting: manual journal entry CRUD** — `JournalEntryCreate` Vue
  page with multi-line entry, draft/post toggle, live balance footer;
  draft entries can be deleted, posted entries are immutable.
- **Accounting: opening balances wizard** — new `OpeningBalances` page
  seeded from active balance-sheet accounts; `RecordOpeningBalancesAction`
  posts a balanced opening entry on demand, plugging the diff into
  account 9000.
- **Settings: per-organisation module toggles** — organisation owners can
  now enable or disable feature modules (budgets, year-end closing, social
  charges, assets, payroll, etc.) from Settings → Modules without touching
  environment variables.
- **Banking: BIC field for strict pain.001 (FF01)** — bank account form
  now accepts a BIC/SWIFT code required for SEPA FF01-compliant pain.001
  exports.
- **Banking: BIC autofill from IBAN** — entering an IBAN auto-populates the
  BIC field via lookup, reducing manual entry errors.
- **Security: organization API token audit log** — every API token request
  against an organisation is now recorded in the activity log.
- **Security: defense-in-depth `authorize()` on API FormRequests** — all
  API form requests explicitly enforce authorization so policy checks
  cannot be accidentally bypassed.
- **API: invoice line cap** — `POST /invoices` rejects payloads with more
  than 500 lines, preventing runaway memory usage.
- **Jobs: harden `GenerateRecurringInvoicesJob` retry policy** — back-off
  and failure handling improved to avoid silent drops on transient errors.

### Changed
- **Banking: QR-IBAN moved to bank account** — the QR-IBAN field has been
  relocated from the payment initiation form to the bank account settings,
  so it is configured once per account rather than per payment.
- **UI: contact form redesigned** — contact create/edit pages now use a
  compact tabbed layout (general, address, banking) replacing the previous
  single-scroll form.
- **UI: status badges** — replaced inline `<span>` badges with the shared
  `Badge` component across `FiscalYears/Index`, `Billing/Plans`, and
  `SaasAdmin/Dashboard`; `statusClasses.js` now exports variant-name maps
  instead of raw CSS class strings.

### Fixed
- **Banking: pain.001 SEPA SvcLvl + auto BIC hotfix** — corrects missing
  `SvcLvl` element and auto-fills BIC for SEPA transfers in generated
  pain.001.001.09 files.
- **Banking: pain.001 `ReqdExctnDt` fix (FF01)** — execution date element
  was malformed for FF01 (instant credit transfer); now emits a valid date
  string.
- **Banking: pain.001 download hotfix** — fixes a regression where the
  download response was empty after the initial pain.001 implementation.
- **Reconciliation: combobox overflow + paid invoices** — dropdown no longer
  overflows its container in a modal; paid invoices are now visible in the
  reconciliation matching list.
- **Banking: QR-IBAN field label clarified** in the bank account form.
- **HTTP: trust reverse proxy headers for HTTPS detection (#18)** — fixes
  `secure` cookie / redirect issues when running behind nginx/Cloudflare;
  adds `TrustedProxiesTest` coverage.
- **Accounting: idempotent chart-of-accounts seeding** — `ChartTemplateService`
  no longer fails when re-seeding an organisation that already has matching
  account codes (root cause of duplicate-code-on-org-create errors).
- **Scheduler: heartbeat HTTP errors are swallowed** — `routes/console.php`
  pins short connect/read timeouts and catches transport exceptions so a
  flaky heartbeat endpoint can no longer block the scheduler tick.
- **i18n: missing fiscal-year translations** for de/fr/it (PR #17 only
  shipped the English keys).
- **Security: secrets and tokens redacted from User activity log** — API
  keys and token values are no longer stored in plain text in activity
  log payloads.
- **Invoicing: N+1 queries eliminated** — Invoice relations are now
  eager-loaded, removing per-row queries on list and export views.
- **Signup: repair accounts schema + free-plan copy + registration gate** —
  fixes a schema inconsistency that caused 500 errors on new sign-ups.

### Security
- **postcss CVE GHSA-qx2v-qp2m-jg93** — bumped `vue` to 3.5.34 and `vite`
  to 8.0.13 to force transitive `postcss` to ≥ 8.5.10; added
  `pnpm.overrides` as a lockfile-level safety net.

### Dependencies
- `tailwindcss` 4.2.2 → 4.3.0
- `@tailwindcss/vite` 4.2.2 → 4.3.0
- `vue` 3.5.32 → 3.5.34
- `vite` 8.0.8 → 8.0.13

### Internal
- `phpunit.xml`: removed hardcoded `APP_BASE_PATH=/var/www/html` that
  caused test suite failures on non-Docker CI runners.
- CI: pinned `gitleaks/gitleaks-action` to v2.3.9 and opted into Node 24
  runners to silence Node 20 deprecation warnings.

### Docs
- `INSTALL.md`: fixed manual installation commands (were incorrectly using
  `vendor/bin/sail`); added **Upgrading** section for both Docker and
  manual installs; bumped Node.js minimum to 22+.

---

## [3.3.0] — 2026-05-12

### Added
- **Migration orchestrator**: warn when account-mapping target set exceeds soft cap

### Changed
- Remove stale PHPStan baseline entry for ExpenseReconciler

---

## [3.2.0] — 2026-05-06

Outbound payment initiation, contacts unification, and Swiss letter PDF
polish.

### Added
- **Banking: outbound payments via pain.001** — new “Payments to send” page
  aggregates payable expenses (supplier IBAN required, not yet posted),
  lets you pick a debtor account and execution date, and downloads a valid
  ISO 20022 pain.001.001.09 batch ready for any Swiss e-banking. Built on
  a provider-agnostic `PaymentInitiationProviderInterface` so an EE bLink
  push provider can plug in later without touching callers.
- **Banking: justification badges** — reconciliation views surface an amber
  badge when a reconciled transaction has no invoice/expense or when the
  matched expense has no receipt, plus a header counter to spot the gap.
- **Invoicing: Swiss SN 010130 / DIN 5008 PDF layout** — invoice PDFs now
  draw fold and punch marks on the left edge and place the recipient
  block inside the standard address window so the sheet fits a C5/C6
  window envelope when folded in three.

### Changed
- **Contacts: unified customers and suppliers** — Customer/Supplier
  subclasses removed in favour of a single Contact model. The expense
  form now lists every contact and auto-flags `is_supplier` on save;
  the contacts datatable drops the now-redundant roles column.
- **Sidebar UX** — recurring expenses surfaced at the top level,
  redundant contact children removed, and Trial Balance / Analytical
  Report moved to *Reports* for a clearer Accounting vs Reports split.
- **Security** — relaxed CSP `frame-ancestors` to `'self'` so inline
  receipt previews can render.
- **i18n** — backfilled 17 missing contacts keys in fr/de/it and added
  recurring-expense translations.

### Fixed
- Use-statement ordering in cleanup scripts.

---

## [3.0.0] — 2026-05-05

First full production release. Includes the QA hardening pass, payroll IBAN
support, banking ledger surfacing, billing UX polish, and release/deploy docs.

### Added
- **Payroll: encrypted employee IBAN** — new `iban` field on employees with MOD-97 validation, encrypted at rest, surfaced across create/edit/show forms and DTOs.
- **Payroll: human-friendly journal references** — posted payroll entries now use `PAY-{INITIALS}-YYYY-MM` references for readability in the GL.
- **Banking: ledger movements card** — bank account and reconciliation pages now surface posted GL movements alongside CAMT statement entries.
- **Billing: free-plan CTA** — Plans page shows an explicit "Activate free plan" action for the free tier and tidies the post-register flow.
- **Contacts: full ISO country list** — country selectors across contacts and organization settings now use the complete ISO list via `Intl.DisplayNames`, sharing a single source of truth.
- **i18n** — new translation keys across DE, EN, FR, IT for billing, banking, payroll, settings, and free-plan activation.
- **Production release baseline** — formalized release process with aligned documentation, branch targeting, deploy defaults, and a `RELEASE.md` runbook.
- **Release/deploy consistency** — deployment branch is now configurable via `DEPLOY_BRANCH` in `deploy.php` template.

### Changed
- **Invoicing: due-date sync** — invoice due date now updates automatically from payment terms; QR-bill validation feedback is clearer.
- **Accounting/expenses/settings** — tightened controllers, queries, and validation rules across year-end closing, expense flows, and settings.
- **Documentation alignment** — root docs describe production readiness and use Sail-based command examples for PHP workflows.
- **Branch policy alignment** — contribution and security documentation reference `develop` as the default supported development branch.
- **CI PR coverage** — GitHub Actions now runs for pull requests targeting both `develop` and `main`.
- **UI polish** — minor refinements across auth, onboarding, organizations, expenses, and reporting screens.

### Fixed
- **Payroll: salary slip lazy-load violation** — `EmployeeController::show` now eager-loads slips with their employee, preventing 500s on the employee detail page.
- **Payroll: deduction sign formatting** — deductions now render as `CHF -x` instead of `-CHF x` (QA bug 33).
- **Translations: dynamic key prefixes** — translation checker now ignores keys ending in `_` to avoid false positives on dynamic prefixes.
- **QA hardening** — three batches of fixes across search, banking, expenses, invoices, billing, payroll, settings, dashboard, and Maska input handling.

### Security
- See [2.18.0](#2180--2026-04-14) — invitation guard, invoice lifecycle policies, cross-org IDOR prevention, server-side VAT enforcement, expense account validation, CSP nonce, GDPR retention, AHV at-rest encryption.

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
