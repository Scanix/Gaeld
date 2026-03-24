# Gäld — Internal Operator & EE Guide

> **Private document** — for the Nectoria team only. Not intended for public distribution.

---

## Editions

| Edition | Description |
|---|---|
| **Community (CE)** | Open-source, self-hosted, MIT licensed. All features listed in README.md. |
| **Hosted / EE** | Managed cloud service at app.gaeld.ch. Adds billing, per-org plan enforcement, SaaS admin, auto-reconciliation, and bank sync. Powered by the `gaeld-ee` plugin. |

---

## gaeld-ee Plugin

Lives at `plugins/gaeld-ee/`. Auto-discovered on boot. Never reference it from core code — the plugin hooks into core via the `FeatureFlag::setOrgResolver()` extension point.

### Structure

```
plugins/gaeld-ee/
├── plugin.json
├── config/ee.php              # Stripe keys, trial days, admin email
└── src/
    ├── GaeldEEServiceProvider.php
    ├── Domains/
    │   ├── Billing/           # Stripe subscriptions, plans, invoices
    │   │   ├── Models/        # Subscription, Plan
    │   │   ├── Services/      # BillingService (Stripe API)
    │   │   └── Controllers/   # Signup, billing portal, webhook
    │   └── SaasAdmin/         # Internal admin dashboard
    │       └── Controllers/   # Restricted to SAAS_ADMIN_EMAIL
    └── routes/web.php
```

### EE Routes

| Path | Description | Auth |
|---|---|---|
| `GET /signup` | Self-service tenant signup | Public |
| `GET /billing` | Billing portal & checkout | Authenticated |
| `POST /stripe/webhook` | Stripe event handler | Stripe signature |
| `GET /saas-admin` | SaaS operator dashboard | `SAAS_ADMIN_EMAIL` only |

### Feature Flag Resolution

CE uses global `.env` flags. EE overrides to check per-org subscription:

```php
// In GaeldEEServiceProvider::boot()
FeatureFlag::setOrgResolver(function (string $feature, mixed $org): bool {
    $subscription = $org->activeSubscription;
    if (!$subscription) return false;
    if ($subscription->status === 'trialing') return true; // trial gets everything
    return $subscription->plan->features->contains($feature);
});
```

---

## EE Feature Flags

| Flag | CE default | EE |
|---|---|---|
| `bank_import` | `true` | `true` |
| `auto_reconciliation` | `false` | per plan |
| `bank_sync` | `false` | per plan |
| `saas` | `false` | `true` |
| `automation` | `false` | per plan |
| `multi_currency` | `false` | per plan |
| `api_access` | `false` | per plan |
| `rule_engine` | `false` | per plan |

---

## Release Workflow

### Prepare a hosted release

```bash
php artisan gaeld:release saas          # Enable all EE feature flags
php artisan gaeld:release saas --dry-run  # Preview only
```

### Prepare a CE release (for tagging on GitHub)

```bash
php artisan gaeld:release community     # Disable all EE flags
```

This updates `config/features.php` defaults. Do NOT commit the `saas` config to the public repo — only tag CE releases from the clean public branch.

---

## Deployment (Production)

Deployed via Deployer (`dep deploy`) to `/data/www/gaeld_app/` on `nectoria`.

```bash
dep deploy                        # Deploy from DEPLOY_REPO (defaults to GitHub)
dep deploy --branch=main
```

Key paths on server:
- App: `/data/www/gaeld_app/current/`
- Shared: `/data/www/gaeld_app/shared/` (`.env`, `storage/`)
- PHP-FPM pool: `gaeld` — socket at `/run/php/gaeld.sock`
- Queue worker: `systemd` unit `gaeld-worker`
- nginx config: `/etc/nginx/sites-enabled/gaeld_app.conf`

### Shared `.env` notes

The production `.env` lives at `/data/www/gaeld_app/shared/.env`. Key EE-only variables:

```env
FEATURE_SAAS=true
FEATURE_AUTO_RECONCILIATION=true
FEATURE_BANK_SYNC=true
FEATURE_AUTOMATION=true
FEATURE_API_ACCESS=true
FEATURE_RULE_ENGINE=true

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

SAAS_ADMIN_EMAIL=alexandre.bianchi@nectoria.com

EE_TRIAL_DAYS=14
```

### After deploy

```bash
dep ssh                          # SSH into release
php artisan gaeld:update         # Runs migrations, clears cache, restarts queue
```

---

## Mail (Mailgun)

```
Host:     smtp.eu.mailgun.org
Port:     587 (STARTTLS)
User:     postmaster@mg.gaeld.ch
From:     noreply@gaeld.ch
Domain:   mg.gaeld.ch
```

Password is in the shared `.env` on server (not stored here).

---

## Infrastructure

| Service | Details |
|---|---|
| Server | Debian VPS — SSH alias `nectoria` |
| SSH deploy user | `deploy` |
| SSH admin user | `debian` |
| App domain | `app.gaeld.ch` |
| Landing | `gaeld.ch` (served from `/data/www/gaeld_web/`) |
| Docs | `docs.gaeld.ch` |
| SSL | Let's Encrypt, auto-renew via certbot |
| DB | PostgreSQL — database `gaeld_app` |
| Queue | Redis + systemd `gaeld-worker` |

---

## Git Remotes

```
origin   git@gitlab.nectoria.com:nectoria/products/gaeld/api.git  (private, source of truth)
github   git@github.com:Scanix/Gaeld.git                          (public CE mirror)
```

Push to both after every commit:

```bash
git push origin main
git push github main
```

Force-push requires unprotecting `main` on GitLab first (Settings → Repository → Protected Branches).

---

## Billing & Plans

Plans are seeded via `database/seeders/`. Each `Plan` record has a JSON `features` array matching the feature flag keys above. `BillingService` syncs subscription status from Stripe webhooks (`customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`).

---

## SaaS Admin

Available at `/saas-admin` — restricted to `SAAS_ADMIN_EMAIL` by middleware. Shows:
- All organizations and their subscription status
- Revenue metrics
- Manual plan override (for support)
