# Staging QA Runner

The runner executes the safe browser sweep against staging and writes compact
JSON plus Markdown evidence. It refuses every URL except
`https://staging.home.nectoria.com` and never targets the production branch or
production infrastructure.

## Prerequisites

Install the JavaScript dependencies with Sail:

```sh
vendor/bin/sail pnpm install
```

Playwright needs a browser executable. The application Sail image is an Alpine
PHP image and intentionally does not contain Chromium system libraries. Run
the browser runner on the macOS host, or use a dedicated Playwright image. A
custom executable can be selected with `QA_BROWSER_PATH`.

Install Chromium on the host when needed:

```sh
pnpm exec playwright install chromium
```

## Dry run

The default is non-destructive and does not contact staging:

```sh
vendor/bin/sail pnpm run qa:staging
```

## Staging smoke run

Use a dedicated staging account. Keep credentials in the shell environment or
an ignored local file; they are never written to the generated artifacts.

Copy `scripts/qa/.env.qa.example` to `.env.qa` and replace the placeholder
values locally, or export the variables directly.

```sh
QA_RUN=1 \
QA_EMAIL='qa-user@example.test' \
QA_PASSWORD='use-a-local-secret' \
QA_HEADLESS=1 \
QA_BROWSER_PATH='/path/to/chromium' \
pnpm run qa:staging
```

When `QA_CREATE_ACCOUNT=1`, configure `QA_MAILPIT_URL` to the staging Mailpit
API (usually an SSH tunnel such as `http://127.0.0.1:18026`). The runner finds
the verification message sent to its generated address and follows only the
signed verification URL for that same address.

Set `QA_CREATE_ACCOUNT=1` to use a fresh account and organization for the
run. The account email is generated from the run ID and is never stored in the
report. This currently covers the real signup submission; email verification
requires a `QA_MAILPIT_URL` adapter and is reported as skipped until that
adapter is configured. Cleanup is enabled by default for ephemeral runs and is
restricted to `build-remote`; it deletes only the generated QA user and
organizations whose names contain the current run ID. Set
`QA_CLEANUP_ORGANIZATION=0` only when deliberately retaining the run for
inspection.

Set `QA_PLAN=team` for the full SaaS workflow. `QA_PLAN=free` is useful for the
lower-cost signup and accounting smoke tests; paid conversion checks require a
configured Solo/Team Stripe Checkout adapter.

Set `QA_EXHAUSTIVE=1` with `QA_CREATE_ACCOUNT=1` and `QA_PLAN=team` to run
the dedicated 24-month campaign. It creates a second disposable tenant,
replays 24 months of invoices, expenses, payroll, CAMT.053/CAMT.054 imports,
seven monthly report views, eight VAT settlements, both year-end closures, 14
exports, nine salary certificates, and the signed Stripe webhook lifecycle.
This mode also requires `STRIPE_WEBHOOK_SECRET`, matching the staging endpoint;
the value is read locally and never written to artifacts.

Set `QA_FULL=1` in addition to `QA_EXHAUSTIVE=1` for the literal high-volume
playbook replay: 8 invoices, 15 expenses and 5 invoice emails per month,
multiple CAMT transactions per statement, a 15-line paper opening balance, and
a historical summary entry. It runs only on disposable staging tenants and can
take substantially longer than the functional exhaustive mode.

The runner writes to `storage/app/qa/`:

- `staging-qa-<run-id>.json`: complete machine-readable evidence;
- `staging-qa-<run-id>.md`: compact human-readable summary;
- `screenshots-<run-id>/`: page captures when `QA_CAPTURE_SCREENSHOTS` is not
  `0`.

The current implementation covers the staging-safe UI workflows for phases 0
through 10, including ephemeral signup, Mailpit verification, onboarding,
opening-balance validation, contacts, invoices, expenses, payroll, VAT,
fiscal-year changes, multi-persona permissions, year-end reopen/reclose,
Stripe Test Clocks, accessibility and responsive checks. Tenant creation and
cleanup are enabled only when `QA_CREATE_ACCOUNT=1`; the cleanup is restricted
to the generated QA namespace on `build-remote`.