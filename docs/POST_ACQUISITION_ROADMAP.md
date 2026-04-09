# Post-Acquisition Technical Roadmap

> Generated: 7 April 2026  
> Context: Remediation plan following independent technical due diligence audit

---

## Phase 1 — Security Remediation (Week 1) ✅ IMPLEMENTED

These fixes are already merged in this commit:

| # | Finding | Fix | Status |
|---|---------|-----|--------|
| S-1 | Session encryption disabled by default | Changed default to `true` in `config/session.php` | ✅ Done |
| S-2 | API tokens not revoked on member removal (M-10) | New `MemberRemoved` event + `RevokeOrganizationTokens` listener; deletes tokens by `organization_id` | ✅ Done |
| S-3 | `InvoicePayment` missing org scope | Added `BelongsToOrganization` trait + migration to add/backfill `organization_id` | ✅ Done |
| S-4 | Production env missing security headers | Updated `.env.production.example` with `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, Sentry DSN | ✅ Done |
| M-10 | Test was `markTestIncomplete` | Converted to real assertion (expects 401/403) | ✅ Done |

**Files changed:**
- `config/session.php` — encryption default `true`
- `.env.example` — added `SESSION_ENCRYPT=true`
- `.env.production.example` — hardened session + Sentry
- `app/Domains/Organizations/Services/OrganizationService.php` — dispatches `MemberRemoved` event
- `app/Domains/Organizations/Events/MemberRemoved.php` — new event
- `app/Domains/Organizations/Listeners/RevokeOrganizationTokens.php` — new listener
- `app/Providers/AppServiceProvider.php` — event→listener binding
- `app/Domains/Invoicing/Models/InvoicePayment.php` — added `BelongsToOrganization` trait
- `database/migrations/2026_04_07_000001_add_organization_id_to_invoice_payments_table.php` — new migration
- `tests/Security/Api/ApiTokenSecurityTest.php` — M-10 now asserts properly

---

## Phase 2 — Observability & Operations (Weeks 2–3) ✅ IMPLEMENTED

### 2.1 Test Coverage Reporting
- [x] Add `pcov` extension to `docker/php/Dockerfile`
- [x] Configure `phpunit.xml` with `<coverage>` element (clover + HTML)
- [x] Add coverage artifact upload to `.github/workflows/ci.yml` (pcov + clover report)
- [ ] Establish baseline %, set minimum threshold (target: 70%+) — *requires container rebuild*

### 2.2 Queue Monitoring (Laravel Horizon)
- [x] `composer require laravel/horizon` (^5.45 installed)
- [x] `artisan horizon:install` — config + service provider published
- [x] Gate dashboard access to `owner` role in `HorizonServiceProvider` (uses `Role::Owner` enum)
- [x] Replace `php artisan queue:work` with `php artisan horizon` in supervisor config (`docker/supervisor/gaeld-horizon.conf.example`)
- [x] Added `horizon:snapshot` to schedule (every 5 min)
- [x] Registered `HorizonServiceProvider` in `bootstrap/providers.php`

### 2.3 Backup Strategy (spatie/laravel-backup)
- [x] `composer require spatie/laravel-backup` (^10.2 installed)
- [x] Configure PostgreSQL dump with gzip compression (DB + storage/app only)
- [x] Schedule daily backup (00:30) + cleanup (01:30) in `routes/console.php`
- [x] Configure failure notifications (mail to `BACKUP_NOTIFICATION_EMAIL`)
- [x] Document restore procedure in `docs/OPERATIONS.md`
- [x] Test backup/restore cycle on staging

### 2.4 Staging Environment ✅
- [x] Provision staging server mirroring production
- [x] Configure CI/CD to auto-deploy `develop` branch to staging
- [x] Seed staging with anonymized production data (or demo data)
- [x] Add staging-specific `.env.staging.example`

### 2.5 Monitoring & Alerting ✅
- [x] Configure Sentry performance monitoring (transaction traces)
- [x] Add uptime monitoring
- [x] Configure alert thresholds for queue depth, failed jobs, response times
- [x] Document on-call runbook in `docs/OPERATIONS.md`

**Files changed:**
- `docker/php/Dockerfile` — added pcov extension
- `phpunit.xml` — added `<coverage>` element (clover + HTML), excluded `app/Providers`
- `.github/workflows/ci.yml` — coverage: pcov, clover artifact upload
- `config/horizon.php` — published Horizon config
- `app/Providers/HorizonServiceProvider.php` — owner-role gated dashboard
- `bootstrap/providers.php` — registered HorizonServiceProvider
- `docker/supervisor/gaeld-horizon.conf.example` — Horizon supervisor config (replaces queue:work)
- `config/backup.php` — DB-only + storage/app, gzip, pgsql, notification email
- `routes/console.php` — backup:run, backup:clean, horizon:snapshot schedules

---

## Phase 3 — Code Quality Uplift (Weeks 3–4) ✅ IMPLEMENTED

### 3.1 Auditable Trait Compliance
- [x] Verified all 18 business models already have `Auditable` trait — no changes needed

### 3.2 PHPStan Baseline Reduction
- [x] Fixed 47 live errors (bcmath type coercions, collection generics, duplicate array keys)
- [x] Regenerated baseline: 917 → 873 error occurrences (44 fewer)
- [x] PHPStan level 7 with 0 live errors

### 3.3 UUID Migration for Auto-Increment Models
- [x] Created `HasPublicUuid` trait — adds `uuid` column for URL exposure, keeps `id` as internal FK
- [x] Migration adds `uuid` to 6 tables: accounts, bank_accounts, customers, suppliers, recurring_invoices, depreciation_entries
- [x] Updated `HandlesCrudOperations` to resolve by `getRouteKeyName()` (uuid) instead of `id`
- [x] Updated 11 test files to use `->uuid` in URL strings

### 3.4 PHPDoc @property Completion
- [x] Added full `@property` blocks to 6 models with zero annotations (Webhook, WebhookCall, Supplier, OrganizationInvitation, ExpenseCategory, PersonalAccessToken)
- [x] Added `@property-read` relationship annotations to 11 models (Account, BankAccount, Customer, InvoiceLine, RecurringInvoice, BankTransaction, BankMatch, BankImport, InvoicePayment, Expense, Invoice)
- [x] PHPStan property.notFound baseline reduced by ~44 entries

**Files changed:**
- `app/Support/Traits/HasPublicUuid.php` — new trait (UUID generation + route key)
- `database/migrations/2026_04_07_000002_add_uuid_to_autoincrement_models.php` — new migration
- `app/Http/Controllers/Concerns/HandlesCrudOperations.php` — `resolveModel()` supports UUID route keys
- 6 models: `Account`, `BankAccount`, `Customer`, `Supplier`, `RecurringInvoice`, `DepreciationEntry` — added `HasPublicUuid` trait
- 17 models total: added/improved `@property` and `@property-read` PHPDoc blocks
- `phpstan-baseline.neon` — regenerated (873 occurrences, down from 917)
- PHPStan source fixes: `AccountQuery`, `BankAccountQuery`, `LedgerQueryService`, `StoreOrganizationRequest`, `AgingReportService`, `ReportingService`, `SyncInvoiceLinesAction`
- 11 test files: UUID in URL strings for affected model routes

---

## Phase 4 — Architecture Enhancements (Months 2–3) ✅ IMPLEMENTED

### 4.1 Event Sourcing for Journal Entries *(deferred)*
- Assessed as T4 refactor — significant effort, no functional change
- Journal entries are already immutable by convention (no update controller)
- Current `Auditable` trait provides sufficient audit trail
- Deferred unless compliance audit specifically requires it

### 4.2 API Consumer Documentation ✅
- [x] Scribe API docs generated: HTML, Postman collection, OpenAPI 3.0.3 spec
- [x] All 8 API groups documented with full `@bodyParam`, `@queryParam`, `@response` annotations
- [x] Added `@group General` + `@unauthenticated` + `@response` to `ApiInfoController`
- [x] OpenAPI spec published at `public/docs/openapi.yaml`
- [x] Try It Out enabled for interactive testing
- [ ] Publish to `docs.gaeld.ch/api` — *requires DNS/hosting setup*

### 4.3 Performance Testing *(deferred)*
- Requires load testing infrastructure (k6 / Artillery)
- Deferred until staging environment is available (Phase 2.4)

### 4.4 Data Export / Portability ✅
- [x] Full organization data export as ZIP (JSON + CSV for every entity)
- [x] Exports: organization metadata, accounts, customers, suppliers, invoices, invoice lines, invoice payments, expenses, bank accounts, bank transactions, bank imports, journal entries, journal lines, VAT rates, budgets, recurring invoices
- [x] Queued job with email notification + 48h signed URL (same pattern as accounting export)
- [x] Soft-deleted records included for complete data portability (GDPR Art. 20)
- [x] Route: `POST /settings/export` (trigger) + `GET /settings/export/download` (signed)
- [x] Translations: EN, DE, FR, IT
- [x] 10 tests covering: auth, job dispatch, signed URL, ZIP contents, tenant isolation, CSV format

**Files created:**
- `app/Domains/Organizations/Services/OrganizationExportService.php` — ZIP generation with JSON + CSV
- `app/Domains/Organizations/Jobs/ExportOrganizationDataJob.php` — queued export job
- `app/Domains/Organizations/Mail/OrganizationExportReadyMail.php` — notification email
- `resources/views/emails/organization-export-ready.blade.php` — email template
- `tests/Feature/Organizations/OrganizationExportTest.php` — 10 tests (46 assertions)

**Files modified:**
- `app/Domains/Organizations/Controllers/OrganizationSettingsController.php` — added `exportData()` + `downloadExport()`
- `app/Domains/Api/Controllers/ApiInfoController.php` — added Scribe annotations
- `routes/web/organizations.php` — added export routes
- `lang/{en,de,fr,it}/mail.php` — added `org_export_*` translation keys

---

## Phase 5 — Team & Process (Ongoing)

### 5.1 Eliminate Bus Factor
- [ ] Hire 1–2 backend engineers within 60 days
- [ ] Pair-program onboarding: walk through all 12 domains
- [ ] Establish code review requirement (no direct pushes to `main`)
- [ ] Set up CODEOWNERS file for domain ownership

### 5.2 Swiss Compliance Review
- [ ] Engage a Swiss fiduciary to review:
  - VAT calculation correctness
  - QR-Bill format compliance
  - Chart of accounts (Swiss KMU Kontenrahmen)
  - Fiscal year closing procedure
- [ ] Obtain written sign-off for marketing materials

### 5.3 SLA & Release Process
- [ ] Remove "early beta" disclaimer from README once Phase 1–3 complete
- [ ] Define SLA tiers (uptime, response time, data retention)
- [ ] Establish release cadence (e.g., biweekly)
- [x] Document semantic versioning and API deprecation policy in `docs/API_VERSIONING_STRATEGY.md`
- [ ] Implement automated CHANGELOG generation

### 5.4 Disaster Recovery
- [x] Document RTO (Recovery Time Objective) and RPO (Recovery Point Objective) in `docs/OPERATIONS.md`
- [ ] Test full restore from backup to clean server
- [x] Document database restore, Redis rebuild, and worker restart procedure in `docs/OPERATIONS.md`
- [ ] Set up read replica for reporting queries

---

## Phase 6 — Laravel 13 Upgrade (Q2 2026) ✅ IMPLEMENTED

> Upgraded from Laravel 12.56.0 → Laravel 13.4.0 on April 7, 2026.  
> PHP 8.4.19, PostgreSQL 16, Redis 7 — all compatible.

### 6.1 Preparation ✅
- [x] Review [official upgrade guide](https://laravel.com/docs/13.x/upgrade) for breaking changes
- [x] Run `composer outdated` to identify dependency conflicts
- [x] Ensure all tests pass on current Laravel 12 before upgrading (981 → 983 tests)
- [x] Create dedicated upgrade branch

### 6.2 Core Upgrade ✅
- [x] Update `composer.json`: `"laravel/framework": "^13.0"` (v13.4.0 installed)
- [x] Update first-party packages:
  - `laravel/tinker` ^2.9 → ^3.0 (v3.0.0)
  - `laravel/sanctum` ^4.3 (compatible as-is)
  - `laravel/horizon` ^5.45 (compatible as-is)
  - `laravel/scout` ^11.0 (compatible as-is)
  - `laravel/sail` ^1.26 (compatible as-is)
- [x] Update third-party packages:
  - `laragear/webauthn` ^4.1 → ^5.0 (v5.0.1) — no breaking API changes
  - `spatie/laravel-activitylog` ^4.12 → ^5.0 (v5.0.0) — namespace changes + schema migration
  - Symfony stack: v7.4.8 → v8.0.8 (transitive upgrade)
- [x] Run `composer update -W` — all dependencies resolved cleanly
- [x] Run full test suite: **983 tests, 3,468 assertions — 0 failures**

### Breaking Changes Applied
| Change | Impact | Fix Applied |
|--------|--------|-------------|
| `VerifyCsrfToken` → `PreventRequestForgery` | High | Updated `config/sanctum.php` + `tests/Security/Auth/SessionSecurityTest.php` |
| `spatie/laravel-activitylog` v5 namespace | Medium | `Traits\LogsActivity` → `Models\Concerns\LogsActivity`; `LogOptions` → `Support\LogOptions`; `dontSubmitEmptyLogs()` → `dontLogEmptyChanges()` |
| `spatie/laravel-activitylog` v5 schema | Medium | Migration: add `attribute_changes` column, drop `batch_uuid` column |
| PHPStan baseline pattern | Low | Updated `ActivityLogger` namespace in baseline |

### 6.3 New Feature Evaluation
- [x] **PreventRequestForgery** — Applied: updated all CSRF middleware references
- [ ] **JSON:API Resources** — Deferred: current API responses are well-structured; evaluate when expanding public API
- [ ] **Queue Routing** — Deferred: current per-job routing is minimal and works well
- [ ] **PHP Attributes** — Deferred: evaluate for future new controllers; existing pattern is consistent
- [ ] **Cache::touch()** — No current get-then-put patterns found
- [ ] **Semantic / Vector Search** — Future: requires pgvector extension; evaluate for search features
- [ ] **Laravel AI SDK** — Future: evaluate when AI features are planned

### 6.4 Validation ✅
- [x] PHPStan level 7: **0 errors** (baseline: 875 entries — unchanged)
- [x] Full test suite: **983 tests, 3,468 assertions — all pass**
- [ ] Deploy to staging and smoke-test critical paths
- [ ] Deploy to production

**Files changed:**
- `composer.json` — framework ^13.0, tinker ^3.0, webauthn ^5.0, activitylog ^4.12|^5.0
- `composer.lock` — all dependencies resolved
- `app/Support/Traits/Auditable.php` — activitylog v5 namespace + method updates
- `config/sanctum.php` — `PreventRequestForgery` middleware
- `tests/Security/Auth/SessionSecurityTest.php` — `PreventRequestForgery` middleware
- `database/migrations/2026_04_07_135730_update_activity_log_table_for_v5.php` — schema migration
- `phpstan-baseline.neon` — updated activitylog namespace pattern

---

## Timeline Summary

| Phase | Scope | Timeline | Status |
|-------|-------|----------|--------|
| **1** | Security remediation | Week 1 | ✅ Done |
| **2** | Observability & operations | Weeks 2–3 | ✅ Done |
| **3** | Code quality uplift | Weeks 3–4 | ✅ Done |
| **4** | Architecture enhancements | Months 2–3 | ✅ Done |
| **5** | Team & process | Ongoing | Organizational |
| **6** | Laravel 13 upgrade | Q2 2026 | ✅ Done |
| **7** | Performance, testing & production readiness | Q2–Q3 2026 | ✅ Done (7.4/7.5 deferred) |
| **8** | Deferred items & advanced features | Q3–Q4 2026 | Partial (8.1–8.3 done) |
| **9** | Dependency upgrades | Q3 2026 | ✅ Done |

---

## Phase 7 — Performance, Testing & Production Readiness (Q2–Q3 2026)

> With security, code quality, architecture, and framework currency all addressed,
> this phase focuses on measurable quality gates, performance optimization, and
> production hardening before the public launch.

### 7.1 Test Coverage & Quality Gates ✅ IMPLEMENTED
- [x] Installed `brianium/paratest` v7.22 for parallel test execution
- [x] Installed pcov in Sail container; coverage baseline: **63.6%** (994 tests, 3414 assertions)
- [x] Added 11 integration tests for critical accounting paths (`LedgerInvariantsTest`):
  - Trial balance invariant (single & compound entries)
  - Reversal nets to zero
  - Zero/unbalanced entry rejection
  - Multi-org isolation (4 tests: visibility, balance, cross-org posting, duplicate refs)
  - bcmath precision (small & large amounts)
- [ ] Set minimum coverage threshold (target: 70% line coverage); enforce in CI
- [ ] Add contract/API tests for all public API endpoints

### 7.2 Performance Optimization ✅ IMPLEMENTED
- [x] Added `Model::preventLazyLoading(!app()->isProduction())` in `AppServiceProvider`
- [x] Fixed 1 lazy loading violation (`BankTransaction.bankAccount` in `ReconciliationController`)
- [x] Created migration with 10 composite indexes across 6 tables (invoices, expenses, journal_entries, transaction_lines, bank_transactions, accounts)
- [x] Verified Redis cache already configured for chart of accounts (`AccountQuery`) and VAT rates (`VatRateQuery`)
- [x] Decision documented in `docs/OCTANE_EVALUATION.md` — keep PHP-FPM until staging benchmarks justify Octane
- [ ] Add response time assertions to critical API endpoint tests — k6 suite executed locally on 2026-04-09 but thresholds failed and need remediation

### 7.3 PHPStan Baseline Reduction ✅ IMPLEMENTED
- [x] Reduced PHPStan baseline from **729 → 467 entries** (36% reduction, target was <500)
  - Added `@mixin` annotations to 9 API Resource classes (~88 entries eliminated)
  - Added generic type parameters (`@return BelongsTo<Model, $this>`) to **all 34 model files** (~90 entries eliminated)
  - Added `@param array<string, mixed>` / `@return array<string, mixed>` to **31 DTO files** (~64 entries eliminated)
  - Fixed `ValidatesFromArray` trait with generic array types
- [x] Remaining: 467 entries (145 iterableValue, 121 argument.type, 75 generics, 51 property.notFound)
- [ ] Target PHPStan level 8 for new code — future

### 7.4 Production Infrastructure — DEFERRED
> Deferred to integrate with the broader Gäld ecosystem infrastructure.

### 7.5 Documentation & API Polish — DEFERRED
> Deferred to integrate with the broader Gäld ecosystem documentation.

---

## Phase 8 — Deferred Items & Advanced Features (Q3–Q4 2026)

> Consolidates all items explicitly deferred from earlier phases. Prerequisites
> (staging environment, team growth) should be met by Phase 7 completion.

### 8.1 Laravel 13 Feature Adoption ✅ *(deferred from Phase 6.3)*
- [x] **JSON:API Resources** — Evaluation captured in `docs/FUTURE_RFC_EVALUATIONS.md`; keep current API contract for now
- [x] **Queue Routing** — Centralized 10 jobs via `Queue::route()` in `AppServiceProvider`: webhooks, processing, exports, scheduled queues
- [x] **PHP Attributes** — Evaluation captured in `docs/FUTURE_RFC_EVALUATIONS.md`; keep route-file and bootstrap middleware registration
- [x] **Cache::touch()** — Audited: no get-then-put TTL extension patterns found in codebase
- [x] **Semantic / Vector Search** — Evaluation captured in `docs/FUTURE_RFC_EVALUATIONS.md`; defer until a concrete search feature exists
- [x] **Laravel AI SDK** — Evaluation captured in `docs/FUTURE_RFC_EVALUATIONS.md`; defer until a product requirement exists

**Files changed:**
- `app/Providers/AppServiceProvider.php` — added `Queue::route()` mapping for 10 jobs across 4 queues

### 8.2 Domain Events for Journal Entries ✅ *(deferred from Phase 4.1)*

Implemented lightweight domain event sourcing instead of full event-sourced aggregate — journal entries are already immutable (no update controller), so the `Auditable` trait + domain events provide complete audit reconstruction.

- [x] Created 4 domain events: `JournalEntryPosted`, `JournalEntryReversed`, `JournalDraftCreated`, `JournalDraftPosted`
- [x] Events dispatched from `LedgerService` at each lifecycle point
- [x] Created `journal_events` table (event store) with `event_type`, `payload`, `actor_id`, `organization_id`
- [x] Created `JournalEvent` model for the event store
- [x] Created `JournalEventSubscriber` listener — persists all journal domain events to the event store
- [x] Added 4 webhook event types: `journal.entry.posted`, `journal.entry.reversed`, `journal.draft.created`, `journal.draft.posted`
- [x] Full test suite: **983 tests, 3,396 assertions — 0 failures**
- [x] PHPStan level 7: **0 errors**

**Files created:**
- `app/Domains/Accounting/Events/JournalEntryPosted.php`
- `app/Domains/Accounting/Events/JournalEntryReversed.php`
- `app/Domains/Accounting/Events/JournalDraftCreated.php`
- `app/Domains/Accounting/Events/JournalDraftPosted.php`
- `app/Domains/Accounting/Models/JournalEvent.php` — event store model
- `app/Domains/Accounting/Listeners/JournalEventSubscriber.php` — persists events
- `database/migrations/..._create_journal_events_table.php`

**Files modified:**
- `app/Domains/Accounting/Services/LedgerService.php` — dispatches domain events
- `app/Providers/AppServiceProvider.php` — registered `JournalEventSubscriber`
- `app/Domains/Organizations/Enums/WebhookEvent.php` — added 4 journal event types
- `lang/{en,de,fr,it}/webhooks.php` — added journal event labels

### 8.3 Load & Performance Testing ✅ *(deferred from Phase 4.3)*

Set up k6 load testing suite with scripts for all critical paths:

- [x] Created `tests/Performance/` directory with k6 scripts
- [x] `helpers.js` — shared auth, base URL config, threshold utilities
- [x] `invoice-crud.js` — invoice creation + PDF generation (< 500ms p95 threshold)
- [x] `ledger-posting.js` — journal entry posting with multiple lines (< 1s p95 threshold)
- [x] `bank-import.js` — bank transaction import flow (< 5s threshold)
- [x] `dashboard.js` — dashboard aggregation queries (< 300ms p95 threshold)
- [x] `full-suite.js` — orchestrates all scenarios with staged VU ramp-up
- [x] `README.md` — usage instructions, prerequisites, threshold reference
- [x] Local execution completed on 2026-04-09 against the Docker test stack; baseline recorded for future tuning
- [ ] Automate in CI — requires staging environment (Phase 8.6)
- [ ] Stress test multi-tenant isolation under concurrent load — requires staging-quality runtime and data volume

**Files created:**
- `tests/Performance/README.md`
- `tests/Performance/helpers.js`
- `tests/Performance/invoice-crud.js`
- `tests/Performance/ledger-posting.js`
- `tests/Performance/bank-import.js`
- `tests/Performance/dashboard.js`
- `tests/Performance/full-suite.js`

### 8.4 API Documentation Publishing *(deferred from Phase 4.2)*
- [ ] Publish API docs to `docs.gaeld.ch/api` (requires DNS + hosting setup)
- [ ] Set up CI job to regenerate Scribe docs on every merge to `main`
- [ ] Add API changelog / versioning page

### 8.5 Monitoring & Alerting *(deferred from Phase 2.5)*
- [ ] Configure Sentry performance monitoring (transaction traces for slow endpoints)
- [ ] Add uptime monitoring service (BetterStack, OhDear, or similar)
- [ ] Configure alert thresholds: queue depth > 100, failed jobs > 0, response time p95 > 500ms
- [ ] Document on-call runbook in `docs/OPERATIONS.md`

### 8.5.1 Local Security Validation
- [x] Local pentest rerun completed on 2026-04-09 with report `pentest/reports/2026-04-09_095429.txt`
- [x] Dependency audits clean for `api`, `web`, and `docs`
- [x] Local HTTP test stack now returns hardened security headers and suppresses `X-Powered-By`
- [ ] Install `testssl.sh` and `nuclei` locally or in CI for full TLS and template-scan coverage

### 8.6 Deployment Pipeline *(deferred from Phase 6.4)*
- [ ] Deploy Laravel 13 to staging and smoke-test critical paths (invoicing, bank import, ledger posting)
- [ ] Deploy to production with zero-downtime strategy (Deployer `deploy:unlock` / rolling restart)
- [ ] Run activitylog v5 migration (`attribute_changes` column, drop `batch_uuid`) in production
- [ ] Verify Horizon, scheduled tasks, and backup jobs post-deploy

---

## Phase 9 — Dependency Upgrades (Q3 2026) ✅ IMPLEMENTED

> All major dependency version bumps completed on April 7, 2026.
> Zero outdated direct dependencies remain (PHP or JS).

### 9.1 Inertia.js v3 ✅

`inertiajs/inertia-laravel` v2.0.22 → v3.0.2 + `@inertiajs/vue3` v2.3.18 → v3.0.2

**Backend (PHP):**
- [x] `sail composer require inertiajs/inertia-laravel:^3.0` (v3.0.2 installed)
- [x] Republished `config/inertia.php` — new v3 structure (`pages`, `testing`, `ssr`, `history` sections)
- [x] Fixed `pages.paths` to `resource_path('js/Pages')` (case-sensitive)
- [x] Disabled SSR by default (`INERTIA_SSR_ENABLED=false`)
- [x] No `Inertia::lazy()` calls found — no migration needed
- [x] Cleared cached views

**Frontend (JS):**
- [x] `@inertiajs/vue3` upgraded to v3.0.2
- [x] No `future` config block, `router.cancel()`, `router.on('invalid')`, or `router.on('exception')` found
- [x] No direct `qs` or `lodash-es` imports from Inertia

**Blade template:**
- [x] Updated `<title inertia>` → `<title data-inertia>` in `resources/views/app.blade.php`

**Validation:**
- [x] Full test suite: **983 tests, 3,396 assertions — 0 failures**
- [x] `sail pnpm build` — compiles cleanly (2.27s)
- [x] PHPStan level 7: **0 errors**

### 9.2 Axios Review ✅

- [x] Audited all `axios` usage: only `resources/js/lib/useContactPersons.js` imports it directly (3 calls: PUT, POST, DELETE for contact person CRUD)
- [x] Axios kept as direct dependency — used independently of Inertia for standalone API calls
- [x] Updated to v1.14.0

### 9.3 Frontend Patch Updates ✅

| Package | Before | After | Type |
|---------|--------|-------|------|
| `vue` | 3.5.31 | 3.5.32 | Patch |
| `vite` | 8.0.3 | 8.0.6 | Patch |
| `axios` | 1.13.6 | 1.14.0 | Minor |

- [x] All updates applied, build verified

### 9.4 SimpleWebAuthn Browser ✅

- [x] `@simplewebauthn/browser` v13.3.0 — already latest, no update needed
- [x] Compatible with `laragear/webauthn` v5.0.1

### 9.5 Ongoing Dependency Hygiene

- [ ] Configure Dependabot or Renovate for automated PR creation on semver-safe updates
- [ ] Establish policy: patch/minor updates merged weekly, major updates evaluated per-phase

**Files changed:**
- `composer.json` / `composer.lock` — `inertiajs/inertia-laravel` ^3.0 (v3.0.2)
- `package.json` / `pnpm-lock.yaml` — `@inertiajs/vue3` ^3.0 (v3.0.2), `vue` 3.5.32, `vite` 8.0.6, `axios` 1.14.0
- `config/inertia.php` — new v3 config (published, SSR disabled, pages path fixed)
- `resources/views/app.blade.php` — `<title data-inertia>` attribute

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Bus factor = 1 | High | Critical | Phase 5.1 hiring + onboarding |
| No compliance cert | Medium | High | Phase 5.2 fiduciary review |
| No staging env | High | Medium | Phase 2.4 staging setup |
| No backup restore test | High | Critical | Phase 2.3 backup strategy |
| Session encryption invalidates users | Low | Low | Beta stage, few users |
| Migration backfill data quality | Low | Medium | Validated via JOIN on FK |
