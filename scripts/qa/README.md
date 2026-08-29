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
adapter is configured. Do not enable organization cleanup until the run has
been reviewed: the application currently exposes organization deletion but no
user-account deletion route, so full cleanup requires a separately audited
staging CLI adapter.

The runner writes to `storage/app/qa/`:

- `staging-qa-<run-id>.json`: complete machine-readable evidence;
- `staging-qa-<run-id>.md`: compact human-readable summary;
- `screenshots-<run-id>/`: page captures when `QA_CAPTURE_SCREENSHOTS` is not
  `0`.

The current implementation covers the safe authenticated navigation and
responsive checks for phases 0 through 10. Destructive workflows, tenant
creation, Stripe Test Clocks, CLI fixture preparation, and cleanup belong in
explicit phase adapters and are intentionally not enabled by the base smoke
runner.