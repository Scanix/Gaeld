# Gäld — Open-Source Swiss Accounting Platform

Gäld is an open-source accounting platform designed for freelancers and small businesses in Switzerland.

## Editions

- **Community Edition** — Free, open-source, self-hosted
- **SaaS Edition** — Hosted, subscription-based (additional features via feature flags)

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Laravel + Inertia.js + Vue 3
- **Database:** PostgreSQL
- **Cache / Queue:** Redis
- **Documentation:** Docusaurus (external)

## Quick Start (Docker)

```bash
cp .env.example .env
docker-compose up -d
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan gaeld:install
```

Then visit `http://localhost:8080` to access the application.

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

Interactive first-run setup. Runs migrations, creates the admin user and organization, seeds the Swiss chart of accounts and VAT rates.

```bash
php artisan gaeld:install          # Interactive prompts
php artisan gaeld:install --demo   # Include demo data (clients, invoices, expenses)
php artisan gaeld:install --no-interaction  # Non-interactive with sensible defaults
```

### `gaeld:release`

Prepare the application for a specific edition release. Sets feature flags and optimizes for production.

```bash
php artisan gaeld:release community   # Disable SaaS features
php artisan gaeld:release saas        # Enable all features
php artisan gaeld:release saas --dry-run  # Preview changes without applying
```

### `gaeld:update`

Run after pulling a new version. Handles migrations, cache clearing, and queue restarts with zero-downtime maintenance mode.

```bash
php artisan gaeld:update
```

## Architecture

Gäld uses a **domain-driven modular architecture**:

```
app/Domains/
├── Accounting/     # Chart of accounts, journal entries, ledger
├── Banking/        # Bank accounts, transactions, sync
├── Contacts/       # Customers and suppliers
├── Expenses/       # Expense tracking
├── Invoicing/      # Invoices, invoice lines, payments
├── Organizations/  # Multi-org support
├── Reporting/      # Financial reports (read-only projection)
└── Users/          # Authentication, user management
```

Domains commonly contain: `Models/`, `Actions/`, `Services/`, `Controllers/`, `Policies/`, `DTOs/`, `Queries/`. Some domains omit sub-packages by design (e.g. Accounting routes all mutations through `LedgerService`, Reporting is a read-only projection with no Models).

## Accounting Engine

- Double-entry accounting
- Every journal entry must balance (SUM debit = SUM credit)
- `LedgerService` handles posting transactions
- Swiss SME chart of accounts included as default seeder

## Feature Flags

Enable/disable features via `.env`:

```env
FEATURE_BANK_SYNC=false
FEATURE_SAAS=false
FEATURE_AUTOMATION=false
```

## Plugin System

Drop plugins into `/plugins` directory. Each plugin includes:

- `plugin.json` — metadata
- `ServiceProvider` — auto-registered
- `routes/`, `migrations/` — optional

## Languages

Supported: English, French, German, Italian, Romansh (EN/FR/DE/IT/RM)

## License

MIT License — See [LICENSE](LICENSE) for details.
