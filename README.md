# Gäld

**Open-source accounting for Swiss freelancers and small businesses.**

Proper double-entry bookkeeping, Swiss QR-Bill invoicing, VAT reporting, and bank reconciliation — built with Laravel and Vue, MIT licensed, fully self-hostable.

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
- **Multi-language** — English, French, German, Italian, Romansh (EN / FR / DE / IT / RM)
- **Plugin system** — extend functionality without touching the core codebase

---

## Getting started

### Docker (recommended)

```bash
cp .env.example .env
docker-compose up -d
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan gaeld:install
```

Visit `http://localhost:8080`. The install wizard walks you through creating your organisation and admin account.

Add `--demo` to seed the database with sample invoices, expenses, and contacts:

```bash
docker-compose exec app php artisan gaeld:install --demo
```

### Manual

```bash
composer install
npm install && npm run build
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
| Backend | Laravel 12 |
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

Issues and pull requests are welcome. For larger changes, please open an issue first so we can discuss the approach.

Please also read the repository-level contribution guide at [../CONTRIBUTING.md](../CONTRIBUTING.md).

```bash
php artisan test          # run the test suite (255 tests)
./vendor/bin/pint         # fix code style
```

Please keep pull requests focused and include tests for new behaviour.

---

## License

MIT — see [LICENSE](LICENSE).
