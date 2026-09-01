# Changelog

All notable changes to Gäld are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.6.5] — 2026-09-01

### Changed
- **Navigation:** separated personal account, organizations, and current
  organization settings in the application navigation.
- **Organization management:** exposed organization switching and team access
  more clearly, including for freelancers, while keeping actions aligned with
  server-side permissions.

## [3.6.4] — 2026-09-01

### Added
- **Subscription billing UX:** local invoice history, server-verified
  Checkout returns, proration previews, lifecycle notifications, and
  idempotent billing reconciliation in the Enterprise Edition.
- **SaaS plan enforcement:** payroll processing routes now enforce the
  organization subscription plan while preserving read access to historical
  payroll data.

### Changed
- Added billing notification preferences and localized billing notification
  messages in English, French, German, and Italian.

## [3.6.0] — 2026-08-20

### Added
- **Community Edition REST API:** organization-scoped journal-entry,
  contact, invoice, expense, and CAMT.053 integration workflows with bearer
  tokens, account-code references, stable JSON resources, and idempotent
  retries. API access is enabled by default and can be disabled with the
  installation-level `FEATURE_API_ACCESS` kill switch.

### Changed
- **Dependencies:** updated Laravel 13, Inertia, Horizon, Stripe PHP 21,
  Symfony, Vite, Tailwind, Vue, and related locked dependencies.
- **Accounting periods:** explicit fiscal-year identity now flows through
  cash-flow reports, exports, archive PDFs, bundles, and archive lazy-loading;
  legacy year URLs remain supported.
- **Plugin frontend boundary:** Community Edition builds no longer contain
  SaaS Admin page sources; enabled plugins register their own frontend pages
  through the manifest-driven Vite registry.

### Fixed
- **SaaS deployment:** EE now uses PSR-7 2.13 and current Stripe/Sentry/Symfony
  dependencies, preventing the Guzzle `asciiToUpper()` runtime mismatch.
- **Archive concurrency:** direct PDF generation now uses the same
  organization-period lock contract as bulk archive generation.
- **Year-end closing:** long fiscal years pass their explicit fiscal-year ID to
  archive generation and log the resolved period boundaries.

### Removed
- **Dead frontend tooling:** removed the unshippable Storybook configuration
  and stories that were absent from clean public clones.
- **Dead WebAuthn helper:** removed the deprecated unreferenced browser helper;
  active passkey flows use `@simplewebauthn/browser`.

## [3.6.2] — 2026-08-25

### Fixed
- **Onboarding banking:** first bank accounts are linked to the organization’s
  `1020` ledger account so balances, expense payments, and reconciliation use
  the same account.
- **Expense VAT UX:** the VAT amount is calculated from the selected rate in
  the form and shown read-only, while server-side VAT calculation remains
  authoritative.
- **Cash Flow:** the Inertia controller now preserves the calculated report
  contract instead of remapping it to obsolete balance and section keys.

## [3.6.3] — 2026-08-31

### Fixed
- **API tokens:** canonical abilities, legacy ability compatibility, wildcard
  handling, organization scoping, and integer expiration values now agree
  between token settings and `/api/v1` authorization.
- **Expenses:** non-cash CHF payments credit the exact gross amount; Swiss
  five-cent rounding remains limited to explicit cash payments.
- **Payroll:** the run preview now uses the same server-side calculator as
  generated salary slips.
- **Reporting:** Cash Flow renders the backend operating, investing, and
  financing contract with the correct cash balances and subtotals.
- **Reconciliation and mobile UI:** empty suggestion maps serialize as JSON
  objects, masked inputs use the Maska v3 API, and Settings tabs fit narrow
  screens.
- **Payroll:** 13th salary, calendar-day unpaid leave, reimbursements,
  historical employee snapshots, salary certificates, queued salary-slip mail,
  and a feature-gated withholding-tax contract now use one calculation path.
- **Fiscal-year settings:** owners can review and apply an approved fiscal-year
  start change on its effective date without mutating the current period early.
- **VAT:** settled periods are locked at the VAT-entry boundary and exact bank
  matching uses the posted settlement reference, amount, and direction.
- **Accounting navigation:** salary-slip journal links now open an authorized
  read-only detail page instead of a missing GET endpoint.

## [Unreleased]

## [3.5.1] — 2026-08-07

### Added
- **UI: Storybook component catalog** — added Storybook 10 with Vue/Vite,
  Tailwind v4 theme support, accessibility checks, and stories for the shared
  Button, FormInput, Modal, and SearchableSelect components.
- **Organizations: onboarding guard** — incomplete users are redirected to
  the setup wizard before entering the dashboard.
- **Organizations: expense category provisioning** — new organizations are
  created with the default expense categories instead of waiting for a visit
  to Settings.

### Changed
- **Invoicing: payment recording UX** — the payment form now pre-fills the
  remaining balance, validates the amount client-side, shows paid/due totals
  and progress, and provides an explicit empty payment-history state.
- **Invoicing: line-item editor** — improved hierarchy and visual distinction
  between item, discount, and note lines, with clearer desktop headings.
- **Contacts: quick creation** — secondary contact fields are progressive and
  country selection is searchable.
- **Expenses: missing references** — an empty category reference state now
  explains the issue and links to Settings instead of failing silently.

### Fixed
- **EE: Stripe payment-method synchronization** — checkout completion now
  re-fetches the Stripe subscription, persists the customer subscription's
  default PaymentMethod, and exposes the real payment-method state to Billing.
- **EE: signup 500** — replaced the failing remote compromised-password check
  with equivalent local password-strength requirements, avoiding a Guzzle
  runtime incompatibility during registration.
- **Billing: trial warning** — the payment-method banner is now based on
  `has_payment_method`, not merely the presence of a Stripe customer.
- **Onboarding: wizard rendering** — fixed Vue template ref unwrapping and
  module prop references that caused the wizard to crash on staging.
- **SaaS: unpaid access** — past-due, paused, canceled, and expired trial
  subscriptions no longer retain access to protected routes.

### Tests
- Full suite passes with 1,242 tests and 4,436 assertions; 13 tests remain
  skipped by design.
- Added regression coverage for onboarding redirects, organization reference
  provisioning, and the EE billing integration.
- Validated the principal staging flow: signup, Stripe trial checkout, email
  verification, onboarding wizard, invoicing, payments, expenses, and reports.

---

## [3.4.3] — 2026-06-01

### Added
- **Anti-spam: hCaptcha integration on signup and password reset** — added
  `HCaptchaRule` validation rule and `<HCaptcha />` Vue component that
  lazy-loads the hCaptcha API and renders a widget when
  `HCAPTCHA_SITE_KEY` / `HCAPTCHA_SECRET_KEY` env vars are set. Wired
  into the public registration flow (EE) and the password reset
  endpoint. Login is intentionally left out (already throttled). The
  rule is no-op without a secret key and during the `testing`
  environment so existing tests stay green.
- **SaaS admin: organization moderation CRUD** — the SaaS admin
  dashboard now lists per-organization usage (members, activity in last
  30 days, paid subscription flag) and exposes Suspend, Reactivate and
  Delete actions plus a drill-down page (`/saas-admin/{id}`) showing
  members, activity counts and subscription history. Suspended
  organizations are blocked at the `EnsureHasOrganization` middleware
  with a 403 (the SaaS admin account remains exempt).
- **SaaS admin: signup kill-switch** — new toggle persists a cache flag
  consumed by `EnforceRegistrationGate`; when active the public signup
  endpoint returns 503 immediately. Useful during incident response or
  when burning down spam.
- **Console: `gaeld:cleanup-spam-orgs` command** — defaults to dry-run.
  Detects suspiciously-named organizations (single-word
  letters-only, configurable `--min-length`) created in the last
  `--days=7` that have no business activity (invoices, expenses,
  contacts, bank transactions, journal entries) and no paid
  subscription, then deletes them through `DeleteOrganizationAction`
  when `--force` is passed.
- **Organizations: soft deletes + suspension fields** — new migration
  adds `deleted_at`, `suspended_at`, `suspended_reason` to the
  `organizations` table. New `DeleteOrganizationAction` detaches all
  members, soft-deletes EE subscriptions, then soft-deletes the
  organization within a transaction and logs `organization.deleted`.

### Fixed
- **EE: duplicate `Subscription::isPaused` declaration causing fatal
  errors in production** — `plugins/gaeld-ee/.../Subscription.php`
  declared `isPaused`, `isTrialExpired`, `getStatus`, `getTrialEndsAt`,
  `getEndsAt` and `getPlan` twice, which surfaced as
  `Cannot redeclare ... isPaused()` fatals on release 223
  (Sentry 7518411333). The second block has been removed; behaviour
  preserved by the earlier definitions.

---

## [3.4.2] — 2026-05-17

### Fixed
- **Invoicing: concurrent invoice number collisions no longer 500** —
  `CreateInvoiceAction` now retries up to five times when the database
  rejects an insert with `invoices_organization_id_number_unique`,
  regenerating the auto-formatted `{PREFIX}-YYYY-NNN` number from the
  current max. Custom user-supplied numbers still surface the original
  uniqueness error instead of being silently rewritten. Closes the
  Sentry-reported `UniqueConstraintViolationException` triggered by
  double-submits and parallel requests.

### Changed
- **Tests: pinned `APP_BASE_PATH` via `tests/bootstrap.php`** — plugin
  vendor autoloaders (notably `plugins/gaeld-ee`) registered after the
  root `ClassLoader` could win Laravel's `Application::inferBasePath()`
  fallback, breaking `parent::setUp()` for any feature test in the root
  suite. The new bootstrap sets `APP_BASE_PATH` before `vendor/autoload.php`
  runs so the application path is deterministic regardless of plugin
  load order.

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
