# Gäld API — Open-Source Readiness Audit

**Date:** 26 March 2026
**Scope:** `/api/` — Full open-source readiness review
**GitHub Repository:** https://github.com/Scanix/Gaeld (public, MIT license)
**Current GitHub branch:** `github/main` at `v1.0.0`
**Current local branch:** `main` at `v1.10.0`

---

## Executive Summary

The Gäld API project is **well-structured and near open-source ready**, with excellent documentation, clean architecture, proper dependency management, and a good test suite. However, there are **critical issues that must be resolved before promoting the public GitHub repository** — most notably private infrastructure details and real company financial data already pushed to the public repo at `v1.0.0`.

### Readiness Score

| Category | Score | Status |
|---|---|---|
| License & Legal | **9/10** | ✅ Ready |
| Documentation | **9/10** | ✅ Ready |
| Security (Secrets/PII) | **3/10** | 🔴 Critical issues |
| Configuration | **8/10** | ✅ Good |
| Dependencies | **9/10** | ✅ Ready |
| Code Quality | **8/10** | ✅ Good |
| CI/CD & DevOps | **6/10** | ⚠️ Needs work |
| Test Coverage | **7/10** | ✅ Acceptable |
| **Overall** | **7/10** | ⚠️ **Not ready — fix critical issues first** |

---

## 1. License & Legal ✅

### Findings

| Item | Status | Notes |
|---|---|---|
| LICENSE file | ✅ | MIT License, Copyright 2026 Gäld Contributors |
| `composer.json` license field | ✅ | `"license": "MIT"` |
| `package.json` private field | ✅ | `"private": true` — appropriate for an application |
| CODE_OF_CONDUCT.md | ✅ | Contributor Covenant v2.1, contact@gaeld.ch |
| CONTRIBUTING.md | ✅ | Comprehensive guide with setup, style, PR process |
| SECURITY.md | ✅ | Responsible disclosure policy, 72h response SLA |
| PHP file license headers | ⚠️ | No license headers in PHP source files |

### Recommendations

- **Optional:** Add a short MIT license header to PHP files, or document in CONTRIBUTING.md that the MIT license in the root `LICENSE` file covers all source files. This is a common practice but not strictly required for MIT.

---

## 2. Documentation ✅

### Findings

| Document | Status | Notes |
|---|---|---|
| README.md | ✅ | Excellent — features, quickstart, architecture overview, config docs |
| INSTALL.md | ✅ | Docker and manual install steps, demo credentials documented |
| CONTRIBUTING.md | ✅ | Code style, PR process, dev setup for all sub-projects |
| CHANGELOG.md | ✅ | Keep a Changelog format, SemVer, detailed entries |
| Architecture docs | ✅ | Domain-driven structure well explained in README |
| Plugin system docs | ✅ | README covers plugin structure, example-plugin provided |
| API docs (Swagger/OpenAPI) | ❌ | No API documentation found (no OpenAPI spec, no Scribe/L5-Swagger) |

### Recommendations

- **Medium priority:** Add API documentation. Consider using [Scribe](https://scribe.knuckles.wtf/) or [L5-Swagger](https://github.com/DarkaOnLine/L5-swagger) to auto-generate API docs from route annotations.
- **Low priority:** The INSTALL.md references `git clone https://github.com/gaeld/gaeld-app.git` — verify this matches the actual GitHub URL (`github.com/Scanix/Gaeld`).

---

## 3. Security — Secrets & PII 🔴 CRITICAL

### 3.1 `deploy.php` — Private Infrastructure Details (CRITICAL)

**Status:** Tracked in git, already pushed to public GitHub at `v1.0.0`.

The `.gitignore` contains a rule for `deploy.php`, but the file was committed before the ignore rule was added (or was force-added). It remains tracked.

| Exposed Data | File | Line |
|---|---|---|
| Private GitLab URL | `deploy.php` | L38: `git@gitlab.nectoria.com:nectoria/products/gaeld/api.git` |
| Production server hostname | `deploy.php` | L60: `'nectoria'` (SSH alias) |
| Production deploy path | `deploy.php` | L62: `/data/www/gaeld_app` |
| Private EE GitLab repo | `deploy.php` | L113: `git@gitlab.nectoria.com:nectoria/products/gaeld/gaeld-ee.git` |
| PHP-FPM version | `deploy.php` | L79: `php8.4-fpm` |
| Systemd service name | `deploy.php` | L134: `gaeld-worker` |

**Required actions:**
1. `git rm --cached deploy.php` to untrack
2. Use [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/) or `git filter-repo` to purge from all history
3. Force-push to GitHub to remove from public repo
4. Rotate any credentials associated with the exposed SSH hostname/path

---

### 3.2 `Import2025AccountingSeeder.php` — Real Company PII (CRITICAL)

**Status:** Tracked in git, already pushed to public GitHub at `v1.0.0`.

This seeder contains **real Nectoria company financial data** including real person names, real Google Cloud invoice IDs, and real financial amounts.

| Exposed Data | Line |
|---|---|
| Company name "Nectoria" | L12, L19, L25, L42, L83 |
| Real Google Cloud invoice IDs | L51-L63: `G127436769`, `G122040592`, etc. |
| Real billing amounts (CHF) | L51-L63 |
| Real person name "Mael Baechtold" | L95 (comment), L133 (vendor field) |
| Real rent amounts | L99-L112: CHF 192/385 |
| TWINT payment reference | L112: `Baechtold` |

**Required actions:**
1. `git rm --cached database/seeders/Import2025AccountingSeeder.php`
2. Purge from git history with BFG/`git filter-repo`
3. Force-push to GitHub
4. If this seeder is needed internally, keep it in the private GitLab repo only

---

### 3.3 `docker/nginx/default.conf` — Production Config Exposed (HIGH)

**Status:** Tracked in git, pushed to public GitHub.

| Exposed Data | Line |
|---|---|
| Production domain `app.gaeld.ch` | L5 |
| SSL certificate paths | L25-L27 |
| Production deploy path `/data/www/gaeld_app/current/public` | L43 |
| PHP-FPM socket path | L56 |

**Recommendation:** Replace with a template (e.g., `default.conf.example`) using placeholders, or parameterize with environment variables. The current file reveals production infrastructure specifics.

---

### 3.4 `docker/supervisor/gaeld-worker.conf` & `docker/systemd/gaeld-worker.service` (MEDIUM)

Both files expose the production deploy path (`/data/www/gaeld_app/current`) and deploy user (`deploy`).

**Recommendation:** Parameterize paths or convert to `.example` templates.

---

### 3.5 `.env.production.example` — Internal Path Leaked (LOW)

Line 3 contains: `Copy to /data/www/gaeld_app/shared/.env` — reveals internal deploy path. This is minor but should be generalized (e.g., `Copy to your shared .env location`).

---

### 3.6 `phpunit.xml` — Test DB Password (LOW)

Contains `DB_PASSWORD=password` for the test database. This is standard practice for local test environments and poses no real risk, but should be noted.

---

## 4. Configuration ✅

### Findings

| Item | Status | Notes |
|---|---|---|
| `.env.example` | ✅ | Complete, no real credentials, all values are placeholders or empty |
| `.env.production.example` | ⚠️ | Uses `CHANGE_ME` placeholders (good), but contains internal path (see §3.5) |
| All 14 config files | ✅ | Properly use `env()` with safe defaults — no hardcoded secrets |
| Feature flags | ✅ | Clean toggle system via `.env` (`FEATURE_SAAS`, `FEATURE_BANK_SYNC`, etc.) |
| `.env` file not tracked | ✅ | Correctly in `.gitignore` and not in git index |
| CORS config | ✅ | Defaults to `APP_URL`, configurable via env |

### Minor Notes

- `config/sanctum.php`: API token expiration set to 129600 minutes (90 days). Consider documenting this decision or reducing for security.
- `resources/js/Components/CookieConsentTranslations.js`: Cookie domain `gaeld.ch` is hardcoded ~12 times. Should be sourced from `VITE_COOKIE_DOMAIN` env var for self-hosters.

---

## 5. Dependencies ✅

### Composer (PHP)

| License | Packages | MIT-Compatible |
|---|---|---|
| MIT | 88 | ✅ |
| BSD-3-Clause | 4 | ✅ |
| BSD-2-Clause | 2 | ✅ |
| Apache-2.0 | 1 | ✅ |
| BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | 2 (nette/schema, nette/utils) | ⚠️ Dual-licensed — BSD clause is compatible |
| LGPL-3.0-or-later | 1 (tecnickcom/tcpdf) | ⚠️ See below |

**LGPL-3.0 note:** `tecnickcom/tcpdf` is LGPL-3.0. LGPL is compatible with MIT **as long as TCPDF is used as a library** (not modified and re-distributed). Since it's a Composer dependency used for PDF generation, this is fine. No action needed.

### NPM (JavaScript)

| License | Packages | MIT-Compatible |
|---|---|---|
| MIT | 96 | ✅ |
| ISC | 4 | ✅ |
| Apache-2.0 | 3 | ✅ |
| MPL-2.0 | 2 | ✅ (file-level copyleft only) |
| BSD-3-Clause | 2 | ✅ |
| CC-BY-4.0 | 1 | ✅ |
| BSD-2-Clause | 1 | ✅ |

**All dependencies are MIT-compatible.** No license conflicts found.

---

## 6. Code Quality ✅

### Findings

| Item | Status | Notes |
|---|---|---|
| Code style tool | ✅ | Laravel Pint configured (`pint.json`) |
| Lint scripts | ✅ | `composer lint` / `composer format` defined |
| Architecture | ✅ | Clean domain-driven structure under `app/Domains/` |
| No TODO/FIXME markers | ✅ | No outstanding TODO/FIXME/HACK comments in PHP source |
| No hardcoded IPs in source | ✅ | Only SSRF protection in `ValidWebhookUrl.php` (correct usage) |
| No debug code | ⚠️ | Git log shows `debug:` commits were made and merged — verify all debug logging was cleaned up |
| PSR-4 autoload | ✅ | Proper namespace structure |

### Recommendations

- Verify debug commits (`e30a3e7`, `bcfcff6`, `4803343`) don't leave debug `Log::error()` calls in production code.
- Consider adding static analysis (PHPStan/Larastan) for open-source quality assurance.

---

## 7. CI/CD & DevOps ⚠️

### Findings

| Item | Status | Notes |
|---|---|---|
| GitHub Actions workflows | ❌ | No `.github/workflows/` directory found |
| GitLab CI config | ❌ | No `.gitlab-ci.yml` in the tracked repo |
| Docker setup | ✅ | `docker-compose.yml` is clean, uses env vars properly |
| Deployment script | ⚠️ | `deploy.php` exposes internals (see §3.1); `deploy.php.example` is clean |
| `deploy-all.sh` | ⚠️ | At root, contains internal hostname references (not in API git repo though) |

### Recommendations

**High priority:** Add GitHub Actions CI workflow for the open-source repo:
```yaml
# .github/workflows/ci.yml
- Run tests (PHPUnit)
- Run linter (Pint)
- Optionally: static analysis (PHPStan)
```

This is a standard expectation for open-source Laravel projects and helps contributors validate their changes.

---

## 8. Test Coverage ✅

### Findings

| Item | Status | Notes |
|---|---|---|
| Test count | ✅ | 70 test files |
| Test framework | ✅ | PHPUnit 13 with proper config |
| Test DB | ✅ | Separate `testing` database configured |
| Test data | ✅ | Uses factories and `@gaeld.local` — no real PII in tests |
| Test fixtures | ✅ | CAMT XML fixtures — no real bank data |
| Feature tests | ✅ | Covers all major domains |
| Unit tests | ✅ | Services, policies, jobs tested |

### Recommendations

- **Optional:** Add test coverage reporting (e.g., `--coverage-text` in CI).
- **Optional:** Aim for higher coverage on critical paths (LedgerService, invoice generation).

---

## 9. `.gitignore` Review ⚠️

### Current Status

The `.gitignore` is comprehensive and correctly excludes:
- `.env`, `.env.backup`, `.env.production`
- `vendor/`, `node_modules/`, `public/build/`
- Storage logs, sessions, cache
- IDE files (`.idea/`, `.vscode/`, `.fleet/`)
- `plugins/gaeld-ee/` (proprietary EE plugin)
- `deploy.php` (but see issue below)

### Issues

| Issue | Severity |
|---|---|
| `deploy.php` is gitignored but still tracked (committed before ignore rule) | 🔴 Critical |
| `storage/framework/testing/disks/local/receipts/test-org/receipt.jpg` is tracked | ⚠️ Low — test artifact shouldn't be committed |

### Missing from `.gitignore`

Consider adding:
```gitignore
# Accounting data (should never be committed)
/2025_comptabilite/

# Audit/internal docs (if ever co-located)
*_AUDIT.md
nectoria-*.md
```

---

## 10. Data Privacy & GDPR ✅

### Positive Findings

- GDPR privacy consent flow implemented (v1.2.0)
- Data export feature available
- IBAN encryption at rest (v1.2.0)
- Activity logging with Spatie package
- WebAuthn / Passkey support for strong authentication

### Issues

- Real PII in `Import2025AccountingSeeder.php` (see §3.2) — violates data minimization principle.

---

## 11. Demo & Showcase Data ✅

### Findings

- `DemoDataSeeder.php` — Uses fictional but realistic Swiss business data. Fake IBANs, fake VAT numbers, `@gaeld.local` emails. **Clean and appropriate.**
- `ShowcaseDataSeeder.php` — Rich fictional data across 4 Swiss language regions. Fake companies (PixelCraft GmbH, NovaTech Sàrl, AlpinCode Sagl). **Clean and appropriate.**
- `SwissChartOfAccountsSeeder.php` — Standard Swiss KMU chart of accounts. **Public knowledge, clean.**
- `SwissVatRatesSeeder.php` — Standard Swiss VAT rates. **Public knowledge, clean.**

---

## 12. Summary of Required Actions

### 🔴 P0 — Must fix before any GitHub push

| # | Action | Files Affected |
|---|---|---|
| 1 | Remove `deploy.php` from git tracking and purge from all history | `deploy.php` |
| 2 | Remove `Import2025AccountingSeeder.php` from git tracking and purge from all history | `database/seeders/Import2025AccountingSeeder.php` |
| 3 | Force-push cleaned history to GitHub (`github` remote) | All |

**Recommended tool:** [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/) or `git filter-repo`

```bash
# Example with git filter-repo:
git filter-repo --path deploy.php --invert-paths
git filter-repo --path database/seeders/Import2025AccountingSeeder.php --invert-paths
git push github main --force
```

### ⚠️ P1 — Should fix before promoting the repo

| # | Action | Files Affected |
|---|---|---|
| 4 | Convert `docker/nginx/default.conf` to a template with placeholders | `docker/nginx/default.conf` → `default.conf.example` |
| 5 | Parameterize deploy paths in supervisor/systemd configs | `docker/supervisor/`, `docker/systemd/` |
| 6 | Generalize internal path in `.env.production.example` comment | `.env.production.example` |
| 7 | Add GitHub Actions CI workflow (tests + lint) | `.github/workflows/ci.yml` |
| 8 | Fix clone URL mismatch in INSTALL.md | `INSTALL.md` |

### 📋 P2 — Nice to have

| # | Action | Files Affected |
|---|---|---|
| 9 | Make cookie domain configurable via `VITE_COOKIE_DOMAIN` | `CookieConsentTranslations.js` |
| 10 | Add API documentation (OpenAPI/Scribe) | New files |
| 11 | Add static analysis (PHPStan/Larastan) | `composer.json`, config |
| 12 | Verify debug log statements cleaned from debug commits | Various |
| 13 | Remove tracked test artifact | `storage/framework/testing/disks/local/receipts/test-org/receipt.jpg` |
| 14 | Review Sanctum token expiration (90 days) | `config/sanctum.php` |

---

## 13. What's Already Good

The project gets a lot right for open-source readiness:

- ✅ MIT License with proper attribution
- ✅ Comprehensive README with quickstart, architecture, and feature list
- ✅ CONTRIBUTING.md, CODE_OF_CONDUCT.md, SECURITY.md — the full governance set
- ✅ Clean `.env.example` with no real credentials
- ✅ All config files use `env()` with safe defaults
- ✅ No hardcoded secrets in PHP/JS source code
- ✅ Domain-driven architecture is clean and well-organized
- ✅ All dependency licenses are MIT-compatible
- ✅ Demo/showcase data is fictional and appropriate
- ✅ Docker setup is clean and usable
- ✅ Test suite exists with proper isolation
- ✅ Code style enforcement with Laravel Pint
- ✅ Plugin system with example plugin for contributors
- ✅ Multi-language support (EN/FR/DE/IT/RM)
- ✅ Feature flag system for optional modules
- ✅ `deploy.php.example` provided as clean template for self-hosters
- ✅ GDPR features (consent, data export, IBAN encryption)

---

*This audit was generated on 26 March 2026. Re-run after addressing P0 items and before making the GitHub repository more visible.*
