# Technical Due Diligence Audit Report

**Subject:** Gäld — Open-source Swiss Accounting Platform  
**Date:** 7 April 2026  
**Auditor:** Independent Technical Expert  
**Mandate:** Pre-acquisition technical assessment  
**Classification:** Confidential  

---

## Executive Summary

Gäld is a **well-architected, early-stage Swiss accounting SaaS** application targeting freelancers and small businesses. The codebase is **20 days old** (first commit: 16 March 2026), built by a **single developer** (bus factor = 1), and already contains ~55,000 lines of application code across a mature domain-driven architecture.

### Overall Assessment

| Dimension | Rating | Comment |
|-----------|--------|---------|
| **Architecture** | A | Clean DDD, proper separation of concerns, plugin system |
| **Security** | A- | Strong OWASP posture; 3 minor findings to remediate |
| **Code Quality** | B+ | PHPStan level 7, consistent patterns, 14 baseline items |
| **Test Coverage** | B+ | 971 tests pass, dedicated security suite, no coverage % measured |
| **Dependencies** | A | All current, zero known CVEs, MIT-compatible stack |
| **Infrastructure** | A- | Docker, CI/CD, zero-downtime deploys; no staging env documented |
| **Documentation** | B+ | Good coding standards, install guide; no API consumer docs |
| **Business Risk** | C | Single contributor, pre-revenue, "early beta" disclaimer |

**Global Score: B+ (Acquisition-viable with known risks)**

---

## 1. Product Overview

### What It Does

Full accounting workflow for Swiss SMEs:
- Double-entry bookkeeping (journal, ledger, trial balance)
- Swiss QR-Bill invoicing (print-ready PDFs)
- Expense tracking with receipt attachments
- Swiss VAT (MWST) — correct rates, VAT report
- Bank reconciliation — CAMT.053 import, transaction matching
- Contact management (customers & suppliers)
- Financial reports — P&L, balance sheet, trial balance, cash flow, aging
- Multi-language (EN, FR, DE, IT, RM)
- Plugin system for extensibility

### Enterprise Edition (not yet monetised)

Feature-flagged capabilities (disabled by default):
- Auto-reconciliation, live bank sync, SaaS multi-tenant billing
- Workflow automation, rule engine, multi-currency
- Advanced permissions, analytics, e-invoicing (ZUGFeRD)
- Tax declaration, multi-entity consolidation, withholding tax

### Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | 12.54 |
| Frontend | Vue 3 + Inertia.js | Vue 3.5 |
| Database | PostgreSQL | 16 |
| Cache / Queue | Redis | 7 |
| Search | MeiliSearch | latest |
| PHP | PHP | 8.4 (requires 8.2+) |
| CSS | Tailwind CSS | 4.2 |
| Auth | Sanctum + WebAuthn + Google2FA | Current |
| Payments | Stripe | SDK 20.0 |
| Error Tracking | Sentry | 4.24 |

---

## 2. Codebase Metrics

### Size

| Metric | Value |
|--------|-------|
| PHP files (application) | 461 |
| PHP lines of code (app/) | 31,727 |
| PHP test files | 143 |
| PHP test lines | 20,101 |
| Vue/JS/TS files (frontend) | 148 |
| Frontend lines | 23,355 |
| Database migrations | 75 |
| Total tests | 971 (+ 2 skipped) |
| Total assertions | 3,422 |
| Test execution time | ~60 seconds |
| **Test-to-code ratio** | **0.63 (test LOC / app LOC)** |

### Git History

| Metric | Value |
|--------|-------|
| Total commits | 212 |
| First commit | 16 March 2026 |
| Latest commit | 4 April 2026 |
| **Project age** | **~20 days** |
| Contributors | 1 (Alexandre Bianchi) |
| **Commit velocity** | **~10.6 commits/day** |
| License | MIT |

### Domain Distribution

| Domain | PHP Files | Purpose |
|--------|-----------|---------|
| Accounting | 59 | Chart of accounts, journal, ledger, VAT |
| Invoicing | 54 | Invoices, payments, QR-Bill, reminders |
| Banking | 50 | Bank accounts, CAMT import, reconciliation |
| Migration | 44 | Data import from other systems |
| Api | 39 | REST API, tokens, webhooks |
| Organizations | 34 | Multi-org, tenant isolation |
| Expenses | 32 | Expense tracking, categories |
| Users | 22 | Auth, profiles, 2FA |
| Contacts | 20 | Customers, suppliers |
| Payroll | 20 | Employees, salary slips |
| Reporting | 18 | Financial reports, PDF/CSV export |
| Assets | 12 | Fixed assets, depreciation |
| **Total** | **404** | 12 bounded domains |

---

## 3. Architecture Assessment

### Strengths

1. **Domain-Driven Design**: Each domain is fully self-contained (Models, Services, Actions, DTOs, Policies, Controllers, Enums, Queries, Requests). This is production-grade DDD — not just folder-by-feature.

2. **Multi-Tenancy**: Three-layer tenant isolation:
   - **Middleware layer**: `EnsureHasOrganization` / `EnsureApiOrganization` resolve org context
   - **Global scope layer**: `BelongsToOrganization` trait auto-filters all queries by org
   - **Policy layer**: Every policy checks `belongsToOrganization()` before granting access

3. **Plugin System**: First-class plugin architecture with manifest-based registration (`plugin.json`), separate migrations, routes, and views. Enterprise features live in a plugin (`gaeld-ee`), not in the core.

4. **Feature Flags**: Clean feature-gated EE functionality. CE and EE share the same codebase without conditional spaghetti.

5. **DTO Pattern**: Consistent use of readonly DTOs with `MapsToSnakeCase` and `OmitsNullValues` traits. Avoids raw array passing.

6. **Action Pattern**: Single-responsibility actions (`CreateInvoiceAction`, `PostJournalEntryAction`) instead of fat controllers or services.

### Concerns

1. **No Event Sourcing for Accounting**: Journal entries use standard Eloquent CRUD, not event sourcing. For a double-entry system, event sourcing would provide an immutable audit trail. Currently mitigated by the `Auditable` trait (Spatie Activity Log) but this is a weaker guarantee.

2. **Monolithic Deployment**: Single Laravel application. No microservice extraction path yet. This is fine for current scale but could limit horizontal scaling of individual domains.

3. **No Background Job Monitoring**: Queue workers exist but no dashboard (Horizon) or dead-letter queue strategy is visible.

---

## 4. Security Assessment

### OWASP Top 10 Compliance

| # | Risk | Status | Evidence |
|---|------|--------|----------|
| A01 | Broken Access Control | **PASS** | Policy-based auth, global org scopes, IDOR tests |
| A02 | Cryptographic Failures | **PASS** | IBANs encrypted, 2FA secrets encrypted, bcrypt passwords |
| A03 | Injection | **PASS** | No raw SQL from user input, parameterised queries, XXE mitigated |
| A04 | Insecure Design | **PASS** | Defence-in-depth (middleware + scope + policy), RBAC |
| A05 | Security Misconfiguration | **MINOR** | Session encryption disabled by default (see finding #1) |
| A06 | Vulnerable Components | **PASS** | Zero CVEs in composer/npm audit |
| A07 | Auth Failures | **PASS** | Rate limiting, strong passwords (12+ chars, HIBP), WebAuthn |
| A08 | Data Integrity Failures | **PASS** | CSRF protection, signed URLs, Stripe webhook verification |
| A09 | Logging & Monitoring | **PASS** | Sentry integration, Spatie activity log |
| A10 | SSRF | **N/A** | No server-side URL fetching features detected |

### Security Test Suite (Dedicated)

| Test Class | Tests | Covers |
|------------|-------|--------|
| HorizontalPrivilegeTest | 10+ | Cross-org data access (IDOR) |
| VerticalPrivilegeTest | 10+ | Role escalation (viewer → admin) |
| BruteForceProtectionTest | 4 | Login/2FA/password reset throttling |
| SessionSecurityTest | 3 | Session fixation, CSRF, regeneration |
| AuthBypassTest | 3 | Token forgery, session hijacking |
| FeatureFlagEnforcementTest | Tests | Feature gate bypass prevention |
| ApiTokenSecurityTest | Tests | Expired/revoked token rejection |
| CamtUploadSecurityTest | 7 | File upload attacks (PHP injection, XXE, MIME bypass) |
| StripeWebhookSecurityTest | 5 | Webhook signature verification |

### Authentication Features

- Session-based auth (web) + Sanctum token auth (API)
- WebAuthn (hardware keys, biometrics) via `laragear/webauthn`
- TOTP 2-factor via `google2fa-laravel`
- Organization-enforced 2FA policy
- Password rules: 12+ characters, mixed case, numbers, symbols, HIBP check
- Rate limits on all auth endpoints (3-10 attempts/minute)

### Findings

#### Finding S-1: Session Encryption Disabled (LOW)

**File:** [config/session.php](../config/session.php)  
**Risk:** Session data stored unencrypted in the database.  
**Impact:** If DB is compromised, session contents are readable.  
**Recommendation:** Set `SESSION_ENCRYPT=true` in production `.env`.

#### Finding S-2: Token Revocation Gap (MEDIUM)

When a user is removed from an organization, their existing API tokens for that org **continue to work** until expiration (18 hours). This is documented in the codebase.  
**Recommendation:** Revoke all user's org-scoped tokens upon removal.

#### Finding S-3: Missing BelongsToOrganization on 3 Models (LOW)

Three org-scoped models lack the `BelongsToOrganization` global scope trait:
- `Api/Models/PersonalAccessToken.php`
- `Invoicing/Models/InvoicePayment.php`
- `Organizations/Models/OrganizationInvitation.php`

**Mitigated by:** Policy checks still verify org membership. Risk is limited to edge cases where queries bypass policies (e.g., internal services).

---

## 5. Code Quality Assessment

### Static Analysis

| Tool | Configuration | Result |
|------|---------------|--------|
| PHPStan | Level 7 / 9 (strict) | **PASS** (0 errors) |
| PHPStan baseline | 14 wontfix items | bcmath type coercions, collection nullability |
| Laravel Pint | PSR-12 + Laravel preset | **PASS** |
| Composer audit | Production deps | **0 vulnerabilities** |
| npm audit | Production deps | **0 vulnerabilities** |

### Coding Standards Compliance

From the internal coding standards audit:

| Layer | Compliance |
|-------|-----------|
| Requests | 100% |
| Queries | 100% |
| Factories | 100% |
| Tests | 98% |
| Controllers | 93% |
| Policies | 89% |
| Actions | 75% |
| DTOs | 65% |
| Enums | 24% |
| Models | 24% |
| Services | 12% |

**Key issues:** 19 models missing `Auditable` trait, 6 models using auto-increment instead of UUID, incomplete PHPDoc on 7 models. These are documented and tracked.

### Money Handling

- All monetary columns use `decimal(16,2)` — never `float`
- Arithmetic uses `bcmath` functions (`bcadd`, `bcmul`, `bccomp`)
- PHPStan baseline contains bcmath type warnings but computations are correct
- **This is critical for an accounting application and is handled properly.**

---

## 6. Test Coverage Assessment

### Test Results

```
Tests:    2 skipped, 971 passed (3,422 assertions)
Duration: 59.26s
```

### Test Suites

| Suite | Scope | Status |
|-------|-------|--------|
| Unit | Services, DTOs, models, parsers, calculators | PASS |
| Feature | HTTP flows, domain integration, controller tests | PASS |
| Security | IDOR, RBAC, auth bypass, file upload, webhooks | PASS |

### Coverage Observations

**Strengths:**
- Every domain has both unit and feature tests
- Dedicated security test suite (rare at this stage)
- Test fixtures and traits for accounting scenarios
- Financial calculations (Swiss rounding, payroll, depreciation) have unit tests

**Gaps:**
- No code coverage percentage is measured or reported
- No performance/load tests
- No contract tests for the API (Scribe generates docs but no consumer-driven contract tests)
- 2 skipped tests (low concern)

**Recommendation:** Enable PHPUnit coverage reporting. Based on the 0.63 test-to-code ratio and domain coverage, estimated coverage is likely 60–75%.

---

## 7. Dependency Analysis

### License Compatibility

All dependencies are MIT, BSD, or Apache 2.0 compatible. **No GPL contamination detected.** The application itself is MIT licensed.

| Package | License | Risk |
|---------|---------|------|
| laravel/framework | MIT | None |
| spatie/* | MIT | None |
| stripe/stripe-php | MIT | None |
| tecnickcom/tcpdf | LGPL-3.0 | **Low** (used as library, not modified) |
| All others | MIT/BSD | None |

### Dependency Count

- **Production PHP deps:** ~40 packages
- **Dev PHP deps:** ~12 packages
- **Frontend deps:** ~25 packages

### Dependency Freshness

All major dependencies are on their latest major versions:
- Laravel 12 (current)
- PHPUnit 13 (current)
- Vue 3.5 (current)
- Tailwind 4.2 (current)

**No legacy or abandoned packages detected.**

---

## 8. Infrastructure & Deployment

### Development Environment

- Docker Compose with 5 services (Laravel, PostgreSQL 16, Redis 7, Mailpit, MeiliSearch)
- Laravel Sail for development tooling
- Custom PHP 8.4 Alpine image with Tesseract OCR
- Non-root container execution (security best practice)

### CI/CD Pipeline

**GitHub Actions workflow** with:
1. Secret scanning (Gitleaks)
2. PHP linting (Pint)
3. Static analysis (PHPStan level 7)
4. Composer security audit
5. npm security audit
6. Full test suite (Unit + Feature + Security)
7. PostgreSQL + Redis services in CI

**Pipeline quality:** Professional-grade. All quality gates are enforced before merge.

### Deployment (Deployer)

- Zero-downtime deployment script (example provided)
- Asset compilation offloaded to local machine, uploaded via rsync
- Automatic migration, cache warm-up, permission sync
- Graceful PHP-FPM restart + queue worker restart
- 5-release retention for rollback

### Gaps

- No staging environment documented
- No infrastructure-as-code (Terraform, Ansible, etc.)
- No backup strategy documented
- No disaster recovery runbook
- No monitoring/alerting beyond Sentry (no Prometheus, Grafana, etc.)

---

## 9. Business Risk Assessment

### Critical Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| **Bus factor = 1** | HIGH | Single developer. All knowledge concentrated. No code owners, no PR reviews. |
| **Pre-revenue** | HIGH | No paying customers. Business model (CE/EE split) defined but untested. |
| **"Early beta" disclaimer** | MEDIUM | The README explicitly warns of breaking changes. No SLA or backwards compatibility guarantee. |
| **No data migration path** | MEDIUM | Migration domain exists for importing from other systems, but no documented path for moving *off* Gäld. |
| **Swiss regulatory compliance** | MEDIUM | Correct VAT rates and QR-Bill support, but no formal compliance certification. |

### Positive Signals

| Signal | Detail |
|--------|--------|
| **High velocity** | 212 commits in 20 days — rapid, sustained development |
| **Architecture maturity** | DDD, plugin system, feature flags — unusual for a 3-week project |
| **Security-first mindset** | Dedicated security tests, encryption, OWASP awareness |
| **Market positioning** | Niche (Swiss SME accounting) with few open-source competitors |
| **MIT License** | No licensing encumbrances for commercial use |
| **Hosted version exists** | app.gaeld.ch suggests SaaS deployment is already operational |
| **Multi-language** | 5 Swiss languages (EN/FR/DE/IT/RM) — good market coverage |

---

## 10. Valuation Considerations

### Assets

1. **Codebase**: ~55K LOC, well-structured, modern stack, ready for team scaling
2. **Domain knowledge**: Swiss accounting rules, QR-Bill spec, CAMT.053 parsing — non-trivial to replicate
3. **Brand & Domain**: gaeld.ch domain, established identity
4. **Plugin ecosystem**: Architecture supports marketplace model
5. **EE feature pipeline**: 13 enterprise features designed and flagged

### Liabilities

1. **Technical debt**: Coding standards compliance varies (12–100% by layer)
2. **Single contributor**: Knowledge transfer cost is high
3. **No revenue proof**: Hosted version exists but unclear usage metrics
4. **Missing coverage metrics**: Testing is good but not measured formally
5. **No formal compliance**: Swiss accounting certifications not obtained

### Cost-to-Reproduce Estimate

Based on 55K LOC × $50–80/LOC (accounting domain complexity):
- **Estimated reproduction cost: CHF 2.7M – 4.4M**
- **Time to reproduce: 12–18 months** with a team of 3–4 engineers

This accounts for the domain-specific knowledge (Swiss QR-Bill, CAMT.053, double-entry accounting, VAT rules) which is the primary value driver — not the raw code volume.

---

## 11. Recommendations for Acquirer

### Immediate (Pre-Close)

1. **Negotiate knowledge transfer**: Require 6–12 month retention/consulting agreement with the developer
2. **Verify SaaS metrics**: Request app.gaeld.ch usage data (users, orgs, transactions)
3. **Run independent security pen-test**: The internal security suite is good but not a substitute for external testing
4. **Clarify IP assignment**: Ensure MIT license and all contributor IP are cleanly assigned

### Post-Acquisition (0–90 Days)

1. **Enable test coverage reporting** and establish a baseline
2. **Fix the 3 security findings** (session encryption, token revocation, missing org scopes)
3. **Hire 1–2 backend engineers** to eliminate the bus factor
4. **Set up staging environment** and monitoring stack
5. **Bring Auditable trait compliance to 100%** on all business models

### Post-Acquisition (90–180 Days)

1. **Obtain Swiss accounting compliance review** from a fiduciary
2. **Implement event sourcing** for journal entries (immutable audit trail)
3. **Add Horizon** for queue monitoring
4. **Build API consumer documentation** (Scribe is installed but docs need verification)
5. **Establish SLAs** and remove "early beta" disclaimer
6. **Reduce PHPStan baseline** from 14 to 0 items

---

## 12. Conclusion

Gäld is a **technically sound, well-architected application** with a clear market niche. The quality of architecture and security practices is **unusually high** for a ~3-week-old project, suggesting significant prior experience and planning by the developer.

The primary risks are **bus factor** (single developer) and **business validation** (pre-revenue, early beta). The technology and codebase are **acquisition-ready** from a technical standpoint, provided the acquirer plans to invest in team building and knowledge transfer.

**Recommendation: Proceed with acquisition**, contingent on satisfactory answers regarding SaaS metrics, developer retention, and a reasonable valuation reflecting the pre-revenue stage.

---

*This report was generated through automated code analysis, full test suite execution, dependency auditing, static analysis, and architectural review. All findings are based on the codebase as of commit on 7 April 2026.*
