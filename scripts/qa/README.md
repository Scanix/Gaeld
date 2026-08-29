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