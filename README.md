# Gäld — Open-Source Swiss Accounting

Gäld is an open-source accounting application for freelancers and small businesses in Switzerland. Double-entry accounting, invoicing, expense tracking, Swiss VAT (MWST), and financial reports — free, self-hosted, MIT licensed.

> **Early beta** — under active development. Expect rough edges.

## Features

- **Double-entry accounting** — full journal, ledger, and trial balance
- **Invoicing** — PDF/QR invoices (Swiss QR-Bill standard)
- **Expenses** — receipt capture and expense reports
- **Swiss VAT (MWST)** — preconfigured rates and reporting
- **Bank reconciliation** — import CAMT.053 files, match transactions
- **Contacts** — customers and suppliers
- **Financial reports** — P&L, balance sheet, trial balance
- **Multi-language** — EN / FR / DE / IT / RM
- **Plugin system** — extend without touching core code

A managed hosted version is available at [gaeld.ch](https://gaeld.ch).

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 |
| Frontend | Inertia.js + Vue 3 |
| Database | PostgreSQL |
| Cache / Queue | Redis |
| Docs | Docusaurus |

## Quick Start (Docker)

```bash
cp .env.example .env
docker-compose up -d
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan gaeld:install
```

Open `http://localhost:8080`.

## Manual Installation

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan gaeld:install
php artisan serve
```

## Artisan Commands

### `gaeld:install`

Interactive first-run setup — runs migrations, creates the admin user and organization, and seeds the Swiss chart of accounts and VAT rates.

```bash
php artisan gaeld:install          # interactive
php artisan gaeld:install --demo   # with demo invoices, expenses, contacts
php artisan gaeld:install --no-interaction
```

### `gaeld:update`

Run after pulling a new version. Handles migrations, cache clearing, and queue restarts gracefully.

```bash
php artisan gaeld:update
```

## Architecture

Domain-driven modular structure under `app/Domains/`:

```
Accounting/     # chart of accounts, journal, ledger
Banking/        # bank accounts, CAMT import, reconciliation
Contacts/       # customers and suppliers
Expenses/       # expense tracking
Invoicing/      # invoices, lines, payments, QR-Bill
Organizations/  # multi-org, tenant isolation
Reporting/      # read-only financial projections
Users/          # auth, user management
```

Each domain contains some combination of `Models/`, `Actions/`, `Services/`, `Controllers/`, `Policies/`, `DTOs/`, `Queries/`. All ledger mutations go through `LedgerService` to guarantee double-entry integrity.

## Feature Flags

Optional features can be toggled in `.env`:

```env
FEATURE_BANK_SYNC=false
FEATURE_AUTO_RECONCILIATION=false
FEATURE_AUTOMATION=false
FEATURE_MULTI_CURRENCY=false
FEATURE_API_ACCESS=false
```

## Plugin System

Drop a plugin into `/plugins`. A plugin is a self-contained Laravel service provider:

```
plugins/my-plugin/
├── plugin.json          # name, version, provider class
├── src/
│   └── MyServiceProvider.php
├── routes/web.php       # optional
└── migrations/          # optional
```

Plugins are auto-discovered on boot — no manual registration needed.

## Contributing

Pull requests are welcome. For significant changes, open an issue first to discuss what you'd like to change. Please keep PRs focused and include tests for new behaviour.

```bash
# Run the test suite
php artisan test
```

Code style follows Laravel conventions (`pint` is configured).

## License

MIT — see [LICENSE](LICENSE).
