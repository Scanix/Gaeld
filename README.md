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

> Current public release: `v3.8.0`. Follow the versioned changelog and release runbook before upgrading a live instance.

[Website](https://gaeld.ch) · [Documentation](https://docs.gaeld.ch) · [Hosted version](https://app.gaeld.ch) · [Release runbook](RELEASE.md)

This repository contains the public Community Edition (CE). The hosted SaaS and
Enterprise Edition (EE) plugin are maintained in private repositories and are
not required for self-hosted CE installations. The CE is a complete accounting
product under AGPL-3.0-or-later; no SaaS subscription or private registry access
is required to install, operate, export, or contribute to it.

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
- **REST API** — integrate journal entries, contacts, invoices, expenses, and
  CAMT.053 imports from external applications
- **Multi-language** — English, French, German, Italian (EN / FR / DE / IT)
- **Plugin system** — extend functionality without touching the core codebase

---

## Getting started

### Docker (recommended)

Install Docker Engine with Docker Compose v2 and start Docker Desktop or the
Docker service before running setup. The command checks Docker immediately and
prints an actionable error if the selected Docker context is unavailable; it
does not install or launch Docker Desktop for you.

On Linux, your user must be allowed to access Docker. If `docker info` reports
`permission denied`, run `sudo usermod -aG docker "$USER"`, start a new login
session (or run `newgrp docker`), and verify with `docker info`.

```bash
./gaeld setup
```

The setup script builds the Docker image, starts all services (PostgreSQL, Redis, Meilisearch, Mailpit), waits for health checks, and runs the interactive installer — with progress indicators at each step.

Add `--demo` for a non-interactive setup with sample data:

```bash
./gaeld setup --demo
```

Visit `http://localhost:8080`. The install wizard walks you through creating your organisation and admin account.

Other useful commands:

```bash
./gaeld artisan migrate    # Run migrations
./gaeld doctor             # Diagnose configuration and service health
./gaeld update             # Apply migrations and rebuild application caches
./gaeld logs               # Tail application logs
./gaeld worker             # Tail queue worker logs
./gaeld status             # Show container status
./gaeld down               # Stop everything
```

For manual installation, upgrades, reverse-proxy configuration, and backup
requirements, see [INSTALL.md](INSTALL.md).

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 |
| Runtime | PHP 8.4+, Node.js 22+, pnpm 11 |
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
Reporting/      — read-only financial reports (P&L, balance sheet, cash flow)
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
FEATURE_API_ACCESS=true
```

The Community Edition includes a versioned REST API at `/api/v1`. It supports
organization-scoped bearer tokens, account and journal-entry operations,
contacts, invoice and expense workflows, and CAMT.053 bank imports. See the
public API documentation for request and response examples.

API tokens use the canonical abilities listed by `GET /api/v1/meta/abilities`,
such as `accounting.view`, `invoicing.create`, and `banking.import`. The
wildcard `*` grants all mapped API operations; omitting `abilities` or sending
an empty array has the same effect. Older resource names such as
`accounts:read` remain accepted and are normalized to their canonical
permissions. Token expiration accepts 1 to 365 days and is stored as an exact
expiration timestamp.

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
./vendor/bin/sail artisan test                      # run the test suite
./vendor/bin/sail bin pint                         # fix code style
./vendor/bin/sail bin phpstan analyse --memory-limit=2G
```

Please keep pull requests focused and include tests for new behaviour.

---

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE).
