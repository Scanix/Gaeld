# Gäld Plugin System — Architecture Audit Report

**Date:** 26 March 2026  
**Scope:** `/api/` — Plugin system architecture, security, and integration  
**Auditor:** Automated Architecture Review

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Plugin System Architecture](#2-plugin-system-architecture)
3. [Plugin Loader Analysis](#3-plugin-loader-analysis)
4. [Plugin Manifest Contract](#4-plugin-manifest-contract)
5. [Plugin Inventory](#5-plugin-inventory)
6. [Extension Points & Integration Hooks](#6-extension-points--integration-hooks)
7. [Security Audit](#7-security-audit)
8. [Testing Coverage](#8-testing-coverage)
9. [Deployment Pipeline](#9-deployment-pipeline)
10. [Documentation](#10-documentation)
11. [Findings Summary](#11-findings-summary)
12. [Recommendations](#12-recommendations)

---

## 1. Executive Summary

Gäld implements a **lightweight, convention-based plugin system** that enables extending the Laravel API without modifying core source code. The system uses a directory-scanning approach with JSON manifests and Laravel ServiceProviders, supporting both the open-source Community Edition (CE) and the proprietary Enterprise Edition (EE) from the same codebase.

### Overall Assessment

| Area | Rating | Notes |
|------|--------|-------|
| Architecture Design | **Good** | Clean separation, minimal coupling |
| Plugin Loader | **Good** | Simple, predictable discovery |
| Security | **Needs Improvement** | Several medium-risk gaps |
| Core Integration | **Good** | Well-designed extension points |
| Testing | **Critical Gap** | Zero tests for EE plugin, minimal for loader |
| Documentation | **Good** | Docs in 4 languages, example plugin provided |
| Deployment | **Good** | Automated EE deployment via Deployer |

---

## 2. Plugin System Architecture

### 2.1 Design Pattern

The plugin system follows a **Service Provider Discovery** pattern:

```
┌─────────────────────────────────────────────────┐
│  Laravel Application Bootstrap                   │
│                                                  │
│  bootstrap/providers.php                         │
│   ├── AppServiceProvider                         │
│   ├── FeatureFlagServiceProvider                 │
│   └── PluginServiceProvider  ◄── Entry point     │
│        │                                         │
│        ├── Scan plugins/ directory               │
│        ├── Read plugin.json manifests            │
│        ├── Register PSR-4 autoloaders            │
│        └── Register plugin ServiceProviders      │
│                                                  │
│  plugins/                                        │
│   ├── example-plugin/                            │
│   │   ├── plugin.json                            │
│   │   └── src/ExamplePluginServiceProvider.php   │
│   └── gaeld-ee/                                  │
│       ├── plugin.json                            │
│       └── src/GaeldEEServiceProvider.php         │
└─────────────────────────────────────────────────┘
```

### 2.2 Key Design Decisions

| Decision | Implementation | Impact |
|----------|---------------|--------|
| **Discovery** | Directory scan + JSON manifest | Auto-discovery, no manual registration |
| **Loading** | `register()` phase (early boot) | Plugins can bind services before boot |
| **Isolation** | Per-plugin PSR-4 autoloader | Plugins are namespace-isolated |
| **Dependencies** | Per-plugin `vendor/` autoload | Plugins manage own Composer deps |
| **Feature gating** | `FeatureFlag` with override hook | CE/EE coexist cleanly |
| **DB schema** | Per-plugin `migrations/` | Schema changes are plugin-scoped |
| **Routes** | Per-plugin `routes/` loaded in `boot()` | Route isolation via prefixes (convention) |
| **Views** | Namespaced view loading | No view collision risk |

### 2.3 Configuration

File: `config/plugins.php`

```php
'enabled' => env('PLUGINS_ENABLED', true),   // Global kill switch
'path'    => base_path('plugins'),            // Plugin directory
'namespace' => 'Plugins',                     // Root namespace
```

---

## 3. Plugin Loader Analysis

**File:** `app/Providers/PluginServiceProvider.php` (84 lines)

### 3.1 Loading Flow

```
register()
  │
  ├── Check config('plugins.enabled') → exit if false
  ├── Check plugins/ directory exists → exit if not
  ├── Scan all subdirectories
  │
  └── For each directory:
       ├── Read plugin.json manifest
       ├── Validate: manifest exists, has 'provider', is 'enabled'
       ├── Load plugin vendor/autoload.php (if exists)
       ├── Register PSR-4 autoloader for src/ directory
       └── Register the plugin's ServiceProvider class
```

### 3.2 Strengths

- **Simple and predictable** — directory scan, no database state
- **Graceful degradation** — missing manifest or disabled plugin silently skips
- **Dual autoloading** — supports both Composer-managed and manually dropped plugins
- **Namespace decoupling** — directory naming (kebab-case) decoupled from PHP namespaces via manifest

### 3.3 Weaknesses & Risks

| Issue | Severity | Description |
|-------|----------|-------------|
| **No load order control** | Medium | Plugins load in filesystem order (`File::directories()`), no priority/dependency resolution |
| **Silent failures** | Medium | Invalid manifests, missing providers, or broken plugins fail silently with no logging |
| **No dependency resolution** | Low | `requires` field in manifest is declared but never validated |
| **No version compatibility check** | Low | No check if plugin is compatible with current Gäld version |
| **No schema validation** | Low | `plugin.json` is not validated against a schema — extra/missing fields go unnoticed |
| **Autoloader stacking** | Low | Each plugin registers a new `spl_autoload_register` — many plugins could slow autoloading |

### 3.4 Missing Capabilities

- **Plugin lifecycle events** — No `onInstall`, `onUninstall`, `onUpgrade` hooks
- **Plugin state management** — No database table tracking installed plugins or versions
- **Conflict detection** — No check for route/service/migration collisions between plugins
- **Plugin API version** — No `min_api_version` / `max_api_version` compatibility gating
- **Plugin disable without removal** — Can only disable via `plugin.json` edit, no runtime toggle

---

## 4. Plugin Manifest Contract

**File:** `plugin.json` (per plugin)

### Current Schema

```json
{
    "name": "string (required — display name)",
    "slug": "string (optional — used for routing/namespacing)",
    "version": "string (optional — semver, unused by loader)",
    "description": "string (optional — human-readable description)",
    "author": "string (optional — author name)",
    "provider": "string (REQUIRED — fully qualified ServiceProvider class)",
    "enabled": "boolean (optional — defaults to true if absent)",
    "requires": "array (optional — declared but NOT enforced)"
}
```

### Observations

- **Only `provider` is enforced** by the loader — all other fields are informational
- **`enabled` defaults to `true`** if absent — explicit opt-out required to disable
- **`requires`** is a dead field — declared in both plugins but never resolved
- **No `slug` enforcement** — slug is only used by plugin routes/views, not by the loader
- **No `license` field** — no distinction between open/proprietary at manifest level

---

## 5. Plugin Inventory

### 5.1 Example Plugin (Template)

| Attribute | Value |
|-----------|-------|
| Slug | `example-plugin` |
| Purpose | Developer reference / template |
| Provider | `Plugins\ExamplePlugin\ExamplePluginServiceProvider` |
| Routes | `GET /plugins/example/` (hello-world) |
| Migrations | None (`.gitkeep`) |
| Views | None (`.gitkeep`) |
| Dependencies | None |

**Assessment:** Clean minimal template. Demonstrates all plugin conventions correctly.

### 5.2 Gaeld EE (Enterprise Edition)

| Attribute | Value |
|-----------|-------|
| Slug | `gaeld-ee` |
| Purpose | SaaS billing, subscription management, admin dashboard |
| Provider | `Plugins\GaeldEE\GaeldEEServiceProvider` |
| Source | Private GitLab: `git@gitlab.nectoria.com:nectoria/products/gaeld/gaeld-ee.git` |
| Git-ignored | Yes — `/plugins/gaeld-ee/` in `.gitignore` |
| Dependencies | `stripe/stripe-php ^13.0` |

**File Structure:**

```
gaeld-ee/
├── plugin.json
├── composer.json
├── config/ee.php
├── INTERNAL.md
├── routes/web.php
├── migrations/
│   ├── 2026_01_01_000001_create_ee_plans_table.php
│   ├── 2026_01_01_000002_create_ee_subscriptions_table.php
│   └── 2026_03_25_000001_add_free_plan.php
└── src/
    ├── GaeldEEServiceProvider.php
    └── Domains/
        ├── Billing/
        │   ├── Controllers/
        │   │   ├── BillingController.php
        │   │   ├── RegistrationController.php
        │   │   └── WebhookController.php
        │   ├── Models/
        │   │   ├── Plan.php
        │   │   └── Subscription.php
        │   └── Services/
        │       └── BillingService.php
        └── SaasAdmin/
            └── Controllers/
                └── SaasAdminController.php
```

**Routes Registered:**

| Method | URI | Controller | Middleware |
|--------|-----|------------|-----------|
| POST | `/stripe/webhook` | WebhookController::handle | `web` (CSRF exempt, Stripe signature) |
| GET | `/signup` | RegistrationController::create | `guest` |
| POST | `/signup` | RegistrationController::store | `guest` |
| GET | `/billing` | BillingController::index | `auth, verified, org, org-2fa, feature:saas` |
| POST | `/billing/checkout/{plan}` | BillingController::checkout | `auth, verified, org, org-2fa, feature:saas` |
| POST | `/billing/portal` | BillingController::portal | `auth, verified, org, org-2fa, feature:saas` |
| GET | `/saas-admin/confirm` | SaasAdminController::confirm | `auth, verified` + email + 2FA gate |
| POST | `/saas-admin/confirm` | SaasAdminController::verifyConfirmation | `auth, verified` + email + 2FA gate |
| GET | `/saas-admin` | SaasAdminController::index | `auth, verified` + email + 2FA gate |
| POST | `/saas-admin/{org}/grant-plan` | SaasAdminController::grantPlan | `auth, verified` + email + 2FA gate |
| POST | `/saas-admin/{org}/revoke-plan` | SaasAdminController::revokePlan | `auth, verified` + email + 2FA gate |

---

## 6. Extension Points & Integration Hooks

### 6.1 Core → Plugin Hooks

The core application provides these explicit extension points used by plugins:

| Hook | Location | Used By | Purpose |
|------|----------|---------|---------|
| `FeatureFlag::setOrgResolver()` | `app/Support/FeatureFlag.php` | gaeld-ee | Override global feature flags with per-org subscription checks |
| `Organization::resolveRelationUsing()` | Eloquent macro (Laravel built-in) | gaeld-ee | Inject `activeSubscription` and `subscriptions` relationships |
| `CheckFeatureFlag` middleware | `app/Http/Middleware/CheckFeatureFlag.php` | gaeld-ee routes | Gate routes behind feature flags |
| `FeatureFlagServiceProvider` | `app/Providers/FeatureFlagServiceProvider.php` | Core + plugins | Registers `feature:` middleware alias and `@feature` Blade directive |
| Inertia `subscription` prop | `app/Http/Middleware/HandleInertiaRequests.php` | Frontend | Exposes subscription data to Vue frontend |

### 6.2 Plugin → Core Dependencies

The EE plugin depends on these core services:

| Core Service | Plugin Usage |
|-------------|-------------|
| `CurrentOrganization` | Injected into BillingController for org context |
| `Organization` model | Macro'd with subscription relationships |
| `User` model | Created during registration, used in auth |
| `FeatureFlag` | Overridden with per-org resolver |
| `HandleInertiaRequests` | Reads `activeSubscription` from org |
| Spatie `PermissionRegistrar` | Team scope set to org ID in registration |
| Session middleware (`org`, `org-2fa`) | Used in billing route middleware stack |

### 6.3 Assessment

**Strengths:**
- Extension points are well-designed — static method hooks (`setOrgResolver`) and Eloquent macros (`resolveRelationUsing`) allow clean override without modifying core
- The `FeatureFlag` class acts as a proper seam between CE and EE
- Frontend integration is clean via the Inertia middleware

**Weaknesses:**
- Only **one** custom hook exists (`FeatureFlag::setOrgResolver`) — future plugins needing to extend other behavior have no formal extension API
- No event system for plugin communication — plugins cannot react to core domain events (e.g., `InvoiceCreated`, `CustomerDeleted`) without manually subscribing
- `resolveRelationUsing()` is a Laravel internal API — may break in future Laravel versions
- The `is_saas_admin` prop computation in `HandleInertiaRequests` is tightly coupled to the EE config — arguably should be a plugin-provided value

---

## 7. Security Audit

### 7.1 Critical Findings

#### FINDING-01: No Input Sanitization on `plugin.json` Provider Class

**Severity:** HIGH  
**File:** `app/Providers/PluginServiceProvider.php` line 49-53  

The `provider` field from `plugin.json` is passed directly to `class_exists()` and `$this->app->register()` without any validation:

```php
$providerClass = $manifest['provider'];
// ...
if (class_exists($providerClass)) {
    $this->app->register($providerClass);
}
```

**Risk:** If an attacker can write to the `plugins/` directory (e.g., via a compromised deployment or file upload vulnerability), they could register arbitrary classes as service providers.

**Mitigation:** The `plugins/` directory is not web-accessible and requires filesystem write access. However, adding a namespace whitelist check would improve defense-in-depth:

```php
if (!str_starts_with($providerClass, 'Plugins\\')) {
    Log::warning("Plugin provider must be in Plugins namespace: {$providerClass}");
    return;
}
```

---

#### FINDING-02: Race Condition in SaaS Admin Grant/Revoke

**Severity:** MEDIUM  
**File:** `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/SaasAdminController.php`

`grantPlan()` uses `Subscription::updateOrCreate()` without database transaction wrapping. Concurrent admin requests could create duplicate subscriptions.

**Recommendation:** Wrap in `DB::transaction()`.

---

#### FINDING-03: Case-Sensitive Email Comparison for Admin Access

**Severity:** MEDIUM  
**File:** `plugins/gaeld-ee/src/Domains/SaasAdmin/Controllers/SaasAdminController.php`

```php
if (! $adminEmail || $request->user()?->email !== $adminEmail) {
    abort(403);
}
```

Email comparison is case-sensitive. RFC 5321 specifies the local-part of an email is case-sensitive, but in practice all major providers treat it case-insensitively.

**Recommendation:** Use `strcasecmp()` or normalize both to lowercase.

---

#### FINDING-04: Webhook Event Deduplication Missing

**Severity:** MEDIUM  
**File:** `plugins/gaeld-ee/src/Domains/Billing/Controllers/WebhookController.php`

Stripe can send the same webhook event multiple times. No idempotency check is performed.

**Risk:** Duplicate events could trigger duplicate subscription state changes.

**Recommendation:** Store processed event IDs and skip already-processed events.

---

#### FINDING-05: Silent Failure in Plugin Loading

**Severity:** LOW  
**File:** `app/Providers/PluginServiceProvider.php`

Failed plugin loading produces no log output. A broken plugin with an invalid manifest or missing provider class is silently ignored.

**Impact:** In production, a broken EE plugin deployment could go unnoticed — the app would run in CE mode without any alert.

**Recommendation:** Add logging for all failure paths:

```php
if (!$manifest || empty($manifest['provider'])) {
    Log::warning("Plugin at {$pluginDir} has invalid manifest");
    return;
}
```

---

#### FINDING-06: Webhook Metadata Trust

**Severity:** LOW-MEDIUM  
**File:** `plugins/gaeld-ee/src/Domains/Billing/Services/BillingService.php`

`syncSubscription()` reads `organization_id` and `plan_id` from Stripe webhook metadata without verifying these IDs exist in the database:

```php
$organizationId = $stripeSubscription['metadata']['organization_id'] ?? null;
$planId = $stripeSubscription['metadata']['plan_id'] ?? null;
```

While Stripe signature verification prevents external manipulation, a compromised Stripe dashboard could inject arbitrary metadata.

**Recommendation:** Validate both IDs exist before operating:
```php
$org = Organization::find($organizationId);
$plan = Plan::find($planId);
if (!$org || !$plan) {
    Log::warning("Webhook sync: invalid metadata", compact('organizationId', 'planId'));
    return;
}
```

---

### 7.2 Positive Security Findings

| Area | Assessment |
|------|-----------|
| **Stripe Webhook Signature** | ✅ Properly verified via `Webhook::constructEvent()` |
| **CSRF Protection** | ✅ Webhook route excluded from CSRF; all other routes use standard CSRF |
| **SQL Injection** | ✅ All queries use Eloquent ORM — no raw SQL |
| **Input Validation** | ✅ Registration uses proper Laravel validation rules |
| **Secret Management** | ✅ All secrets via environment variables — no hardcoded credentials |
| **2FA on Admin** | ✅ SaaS admin requires 2FA + 30-minute confirmation timeout |
| **Git Separation** | ✅ EE plugin gitignored from public repo — proprietary code protected |
| **Route Middleware** | ✅ Billing routes require `auth + verified + org + org-2fa + feature:saas` |
| **Trial Feature Access** | ✅ Trial orgs get full access — controlled via `FeatureFlag` resolver |

---

## 8. Testing Coverage

### 8.1 Current State

| Component | Test Coverage | Status |
|-----------|--------------|--------|
| `FeatureFlag` class | 7 tests in `tests/Feature/FeatureFlagTest.php` | ✅ Covered |
| `CheckFeatureFlag` middleware | Indirectly tested via route tests | ✅ Partial |
| `PluginServiceProvider` (loader) | **No dedicated tests** | ❌ Missing |
| EE Plugin — Registration | **No tests** | ❌ Missing |
| EE Plugin — Billing | **No tests** | ❌ Missing |
| EE Plugin — Webhooks | **No tests** | ❌ Missing |
| EE Plugin — SaaS Admin | **No tests** | ❌ Missing |
| EE Plugin — BillingService | **No tests** | ❌ Missing |
| EE Plugin — Models (Plan, Subscription) | **No tests** | ❌ Missing |
| EE Plugin — FeatureFlag org resolver | **No tests** | ❌ Missing |

### 8.2 Critical Test Gaps

**The entire EE plugin has zero automated tests.** This is the most significant finding of this audit.

#### Recommended Test Plan

**Priority 1 — Security-critical:**
1. WebhookController: Stripe signature validation (reject invalid, accept valid)
2. WebhookController: Event processing for each event type
3. SaasAdminController: Access denied for non-admin emails
4. SaasAdminController: 2FA enforcement
5. RegistrationController: Input validation (email uniqueness, password strength)

**Priority 2 — Business-critical:**
6. FeatureFlag org resolver: Per-org feature resolution (trialing / active / inactive)
7. BillingService: Subscription sync creates/updates correctly
8. Plan: `hasFeature()` method
9. Subscription: Status methods (`isActive()`, `isTrialing()`, etc.)
10. Registration flow: Free plan skips Stripe, paid plan redirects

**Priority 3 — Integration:**
11. PluginServiceProvider: Discovers and loads valid plugins
12. PluginServiceProvider: Skips disabled plugins
13. PluginServiceProvider: Handles missing/invalid manifests
14. Organization `activeSubscription` relationship via macro
15. Inertia subscription prop resolution

---

## 9. Deployment Pipeline

### 9.1 EE Plugin Deployment

**File:** `deploy.php` — task `deploy:ee:plugin`

```
deploy:prepare
  └── deploy:vendors         ← Core Composer deps
       └── deploy:ee:plugin  ← EE plugin step
            │
            ├── Fetch from GitLab (shared/gaeld-ee as cache)
            ├── Copy into release/plugins/gaeld-ee/
            ├── Run composer install --no-dev inside plugin
            │
            └── Continue with assets:build, migrate, cache...
```

**Strengths:**
- ✅ Shallow clone (`--depth=1`) minimizes transfer
- ✅ Shared cache (`shared/gaeld-ee`) avoids re-cloning on each deploy
- ✅ Plugin Composer deps installed separately (`--no-dev`)
- ✅ EE deployed before `artisan:config:cache` — routes and config properly cached
- ✅ Git-ignored from public GitHub repo

**Risks:**
- ⚠️ If GitLab is unreachable during deploy, the entire deploy fails (no fallback)
- ⚠️ No checksum/signature verification on cloned EE code
- ⚠️ `EE_REPO` env var can override the repo URL — if leaked, could point to malicious repo

---

## 10. Documentation

### 10.1 Available Documentation

| Document | Location | Quality |
|----------|----------|---------|
| Plugin Dev Guide | `docs/docs/developer/plugin-guide.md` | ✅ Good — clear structure, examples |
| Plugin Dev Guide (FR) | `docs/i18n/fr/.../plugin-guide.md` | ✅ Translated |
| Plugin Dev Guide (DE) | `docs/i18n/de/.../plugin-guide.md` | ✅ Translated |
| Plugin Dev Guide (IT) | `docs/i18n/it/.../plugin-guide.md` | ✅ Translated |
| Example Plugin README | `plugins/example-plugin/README.md` | ✅ Minimal but clear |
| EE Internal Docs | `plugins/gaeld-ee/INTERNAL.md` | ✅ Comprehensive |
| API README | `api/README.md` (Plugin section) | ✅ Brief but adequate |

### 10.2 Documentation Gaps

- **No formal Plugin API reference** — available hooks, services, and extension points are undocumented
- **No plugin security guidelines** — no guidance on input validation, permission checks, or CSRF handling for plugin developers
- **No plugin testing guide** — no documentation on how to write tests for plugins
- **No migration naming conventions** — EE uses `ee_` prefix but this isn't documented as a requirement
- **No route prefix convention enforcement** — example uses `plugins/example` but EE uses top-level routes (`/signup`, `/billing`)

---

## 11. Findings Summary

### By Severity

| # | Severity | Finding | Section |
|---|----------|---------|---------|
| 1 | **CRITICAL** | Zero test coverage for EE plugin | §8 |
| 2 | **HIGH** | No validation on provider class from plugin.json | §7 FINDING-01 |
| 3 | **MEDIUM** | Race condition in admin grant/revoke operations | §7 FINDING-02 |
| 4 | **MEDIUM** | Case-sensitive admin email comparison | §7 FINDING-03 |
| 5 | **MEDIUM** | No webhook event deduplication | §7 FINDING-04 |
| 6 | **MEDIUM** | `requires` field declared but never enforced | §3.3 |
| 7 | **MEDIUM** | No load order control for plugins | §3.3 |
| 8 | **LOW** | Silent failure during plugin loading | §7 FINDING-05 |
| 9 | **LOW** | Webhook metadata not validated against DB | §7 FINDING-06 |
| 10 | **LOW** | No plugin lifecycle hooks | §3.4 |
| 11 | **LOW** | Missing plugin API reference documentation | §10.2 |

### By Category

| Category | Findings |
|----------|----------|
| Testing | #1 |
| Security | #2, #3, #4, #5, #6, #9 |
| Architecture | #6, #7, #10 |
| Reliability | #8 |
| Documentation | #11 |

---

## 12. Recommendations

### 12.1 Immediate Actions (Do Now)

1. **Add logging to plugin loader failures**
   - Log warnings for invalid manifests, missing providers, disabled plugins
   - Log info when a plugin is successfully loaded
   - Estimated scope: ~10 lines in `PluginServiceProvider.php`

2. **Add namespace validation for provider class**
   - Ensure provider class starts with `Plugins\\` namespace
   - Prevents arbitrary class registration

3. **Fix case-sensitive admin email comparison**
   - Use `strcasecmp()` in `SaasAdminController`

4. **Wrap grant/revoke in DB transactions**
   - In `SaasAdminController::grantPlan()` and `revokePlan()`

### 12.2 Short-Term (Next Sprint)

5. **Add EE plugin test suite** — This is the highest-impact recommendation. Focus on:
   - Webhook signature validation + event handling
   - Registration flow (happy path + edge cases)
   - SaaS admin access control
   - FeatureFlag org resolver behavior

6. **Implement webhook idempotency**
   - Store Stripe event IDs, skip duplicates

7. **Validate webhook metadata against database**
   - Verify `organization_id` and `plan_id` exist before sync

### 12.3 Medium-Term (Next Quarter)

8. **Implement `requires` dependency resolution**
   - Parse `requires` array from manifest
   - Topological sort plugins by dependencies
   - Fail loudly if dependencies are missing

9. **Add plugin load order / priority**
   - Allow `priority` field in `plugin.json` (default: 0)
   - Sort plugins by priority before loading

10. **Create formal Plugin API**
    - Define explicit hooks/events plugins can subscribe to
    - Document available services and their stability guarantees
    - Publish plugin developer security guidelines

### 12.4 Long-Term (Roadmap)

11. **Plugin state database table**
    - Track installed plugins, versions, enabled state
    - Support runtime enable/disable without editing files
    - Track plugin health / last-loaded timestamp

12. **Plugin lifecycle hooks**
    - `onInstall()`, `onUninstall()`, `onUpgrade($from, $to)`
    - Allows plugins to run setup tasks, clean up data, and migrate between versions

13. **Plugin marketplace infrastructure**
    - Schema validation for `plugin.json`
    - Plugin signing / integrity verification
    - Compatibility matrix with Gäld versions

---

*End of audit report.*
