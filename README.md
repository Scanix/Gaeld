<p align="center">
  <img src="public/logo-wide.svg" alt="Gäld" width="280">
</p>

<p align="center">
  <a href="https://github.com/Scanix/Gaeld/actions/workflows/ci.yml"><img src="https://github.com/Scanix/Gaeld/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-AGPL%20v3-blue.svg" alt="License: AGPL v3"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg" alt="PHP 8.4+"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-13-FF2D20.svg" alt="Laravel 13"></a>
</p>

**Open-source accounting for Swiss freelancers and small businesses.**

Proper double-entry bookkeeping, Swiss QR-Bill invoicing, VAT reporting, and bank reconciliation — built with Laravel and Vue, AGPL-3.0-or-later licensed, fully self-hostable.

> Early beta — the core is solid but expect rough edges and breaking changes between versions.

[Website](https://gaeld.ch) · [Documentation](https://docs.gaeld.ch) · [Hosted version](https://app.gaeld.ch)

---

## What it does

Gäld covers the full accounting workflow for a small Swiss business:

- **Double-entry accounting** — journal, ledger, and trial balance with strict debit/credit balance enforcement
- **Invoicing** — professional PDFs with Swiss QR-Bill payment slip (ready to print and send)
- **Expense tracking** — log expenses, attach receipts, categorise by supplier
- **Swiss VAT (MWST)** — correct rates preconfigured, VAT report ready for the tax authority
- **Bank reconciliation** — import CAMT.053 files from your bank, match transactions against invoices and expenses
- **Contacts** — shared customer and supplier directory across all modules
- **Financial reports** — profit & loss, balance sheet, trial balance
- **Multi-language** — English, French, German, Italian (EN / FR / DE / IT)
- **Plugin system** — extend functionality without touching the core codebase

---

## Getting started

### Docker (recommended)

```bash
cp .env.example .env
docker compose up -d --wait
docker compose exec laravel.test php artisan gaeld:install
```

Composer dependencies and the app key are installed automatically on first start. The `--wait` flag ensures everything is ready before you run the installer.

Visit `http://localhost:8080`. The install wizard walks you through creating your organisation and admin account.

Add `--demo` to seed the database with sample invoices, expenses, and contacts:

```bash
docker compose exec laravel.test php artisan gaeld:install --demo
```

### Manual

```bash
composer install
pnpm install && pnpm run build
cp .env.example .env
php artisan key:generate
php artisan gaeld:install
php artisan serve
```

### Updating

```bash
php artisan gaeld:update
```

Runs pending migrations, clears caches, and restarts the queue worker — safe to run on a live instance.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 |
| Frontend | Inertia.js + Vue 3 |
| Database | PostgreSQL |
| Cache / Queue | Redis |
| Docs | Docusaurus |

---

## Architecture

The codebase follows a domain-driven structure. Each business domain is self-contained under `app/Domains/`:

```
Accounting/     — chart of accounts, journal entries, ledger
Banking/        — bank accounts, CAMT import, transaction reconciliation
Contacts/       — customers and suppliers
Expenses/       — expense recording and reporting
Invoicing/      — invoices, payments, QR-Bill generation
Organizations/  — multi-org support, tenant isolation
Reporting/      — read-only financial projections (P&L, balance sheet)
Users/          — authentication, profiles
```

All ledger mutations go through `LedgerService`, which enforces double-entry integrity. The `Reporting` domain is a read-only projection — it never writes to the ledger.

---

## Configuration

Optional features are toggled in `.env`:

```env
FEATURE_BANK_SYNC=false
FEATURE_AUTO_RECONCILIATION=false
FEATURE_AUTOMATION=false
FEATURE_MULTI_CURRENCY=false
FEATURE_API_ACCESS=false
```

---

## Plugin system

Drop a plugin into `/plugins/`. A plugin is a standard Laravel service provider with a manifest:

```
plugins/my-plugin/
├── plugin.json           — name, version, provider class
├── src/
│   └── MyServiceProvider.php
├── routes/web.php        — optional
└── migrations/           — optional
```

Plugins are auto-discovered on boot. See `plugins/example-plugin/` for a minimal working example.

---

## Contributing

Issues and pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before submitting a PR.

```bash
docker compose exec laravel.test php artisan test   # run the test suite
./vendor/bin/pint                                   # fix code style
```

Please keep pull requests focused and include tests for new behaviour.

---

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE).
