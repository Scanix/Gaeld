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

> Current public release: `v3.8.28`. Follow the versioned changelog and release runbook before upgrading a live instance.

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

The `v3.11.0-rc.1` candidate adds an optional public preservation proof profile: validated PDF/A-2b reports, detached CMS signatures, RFC 3161 timestamps, protected S3 retention, independent verification and evidence renewal. The same functions are available in CE and EE. Read the [R3 operating guide](docs/preservation-r3.md). It is not a general GeBüV certification; unconfigured or failed proof stages remain incomplete.

The `v3.10.0-rc.1` candidate adds company-scoped accounting preservation,
frozen content packages, original-document versions, durable preparation and
local integrity controls on top of R1. Read the [R2 operating guide](docs/preservation-r2.md)
before applying its new migration. Cryptographic sealing and GeBüV compliance
are not claimed by this candidate.

The `v3.9.0-rc.1` candidate adds managed Docker and native Linux installation,
adoption of a clean CE v3.8.28 Sail installation, verified encrypted backups,
controlled updates and recovery. It introduces no accounting-schema migration.
See the [installation guide](docs/ce-managed-installation.md) and
[candidate validation record](docs/rc-r1-3.9.0-rc.1.md). Acceptance is pending;
use an isolated test installation until the candidate has been approved.

### Sail development environment

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
./gaeld logs               # Tail application logs
./gaeld worker             # Tail queue worker logs
./gaeld status             # Show container status
./gaeld down               # Stop everything
```

For manual installation, upgrades, reverse-proxy configuration, and backup
requirements for the managed candidate, use the [installation guide](docs/ce-managed-installation.md).
[INSTALL.md](INSTALL.md) describes the existing source and development workflows.

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
Creating or revoking personal tokens through the REST API requires an
unrestricted (`*`) token. Restricted tokens cannot mint broader credentials
or revoke other credentials; token management through the authenticated web
settings remains available.

The shared CE/EE MCP server is available at `/mcp` when `FEATURE_MCP_ACCESS=true`
and `FEATURE_API_ACCESS=true`. It is disabled by default. In SaaS mode, the
organization must have both the dedicated `mcp_access` entitlement and API
access. MCP is included in Team and in an unexpired Team trial, not Cloud Free,
Solo or a Solo trial. A global flag cannot bypass the subscription check.
The same server remains available in self-hosted CE without an EE subscription.
Bearer-token clients are supported; OAuth onboarding is separate work.

Every request checks the current organization and subscription again, including
requests in an existing MCP session. A scheduled cancellation retains access
until the paid end date; expiry, an immediate cancellation, or an effective
downgrade removes hosted MCP access. Re-upgrading to Team restores access for
otherwise valid tokens. Downgrades do not delete tokens, invoices, expenses or
reports, and do not change the established data-export policy. Operators must
deploy the EE plan migration before enabling the two installation flags.
See [the public MCP contract](contract/mcp-contract.json) for the edition boundary.

Create a personal read-only token for an organization owner with:

```bash
vendor/bin/sail artisan gaeld:token ORGANIZATION_UUID --mcp --name="AI assistant"
```

The token defaults to `invoicing.view` and `expenses.view`, expires after 30
days (also subject to Sanctum's configured global expiration), and is shown
only once. Use `--abilities=expenses.view-own` to restrict it to the owner's own
expenses, or `--expires-in-days=7` for a shorter explicit lifetime. Existing
personal API tokens with only these read abilities are also accepted.
Organization tokens, wildcard tokens, and tokens with write abilities are rejected.

Configure the client to use the installation's `/mcp` HTTP endpoint with
`Authorization: Bearer YOUR_TOKEN`. The tools are `list_invoices`, `get_invoice`,
`list_expenses`, `get_expense`, `get_profit_and_loss`, `get_balance_sheet`, and
`get_aging_report`. Tools enforce both token scopes
and the user's current organization permissions; removing membership or revoking
the token removes access. Lists accept date ranges and document filters, use
pages of at most 50 records, and do not calculate global monetary totals.
Amounts are decimal strings with a currency on each document. Files and receipt
paths are not returned. Requested accounting data is transmitted to the chosen
AI provider; its data handling policy applies.

Reports require the explicit token scope `reporting.view` and the user's
`reporting.view` and `accounting.view` permissions. Existing tokens and the
default `--mcp` scopes are unchanged. For example, create a report token with:

```bash
vendor/bin/sail artisan gaeld:token ORGANIZATION_UUID --mcp --name="Reports" --abilities=reporting.view --abilities=invoicing.view --abilities=expenses.view
```

Profit and loss accepts inclusive `from`/`to` dates (at most two years) or an
authorized `fiscal_year_id`. The balance sheet accepts `as_of_date` or an
authorized `fiscal_year_id` and includes the current fiscal year result. Both
use posted ledger entries in the organization's base currency and share the
application's report calculations. Monetary totals remain decimal strings.

Aging accepts `type` (`receivables` or `payables`), `as_of_date`, optional
`currency` (defaults to the organization's currency), `page`, and `per_page`.
Receivables additionally require `invoicing.view`; payables require full
`expenses.view` in both the token and the user's permissions. Own-expense
access never grants organization-wide payable totals. The selected currency
is filtered before calculating totals; no currencies are combined or converted.
Totals cover all matching items, while details are paginated at 50 items at most.
Receivables deduct recorded payments; payables include VAT. Aging uses current
document statuses and recorded balances, with document dates filtered at the
cutoff: it is not a historical reconstruction. Expense dates are used to age
payables because expenses have no contractual due date. Responses identify
these rules and the service cache lifetime of up to 30 minutes.


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
