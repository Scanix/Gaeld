# Production Release Runbook

This runbook defines the release flow for Gäld first full production releases and follow-up releases.

## 1. Preconditions

- Branch: `develop` is green and up to date.
- CI: lint, static analysis, tests, and build all pass.
- Changelog: release section is present with date and highlights.
- Environment: production `.env` is provisioned from `.env.production.example`.

## 2. Validate Release Candidate

Run all release checks from the project root:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer lint
./vendor/bin/sail phpstan analyse --memory-limit=512M
./vendor/bin/sail artisan test
./vendor/bin/sail pnpm build
```

Optional extended checks:

```bash
./vendor/bin/sail artisan test tests/Security
./vendor/bin/sail artisan test tests/Performance
```

## 3. Prepare Deployment Configuration

1. Copy deployment template:

```bash
cp deploy.php.example deploy.php
```

2. Set required environment variables on CI/server:

- `DEPLOY_REPO`
- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_PATH`
- `DEPLOY_BRANCH` (defaults to `develop`)

3. Ensure server services exist and are reachable:

- PHP-FPM (`php8.4-fpm`)
- queue worker service (`gaeld-worker`)
- Redis/PostgreSQL/MeiliSearch as configured

## 4. Execute Deployment

```bash
./vendor/bin/sail composer install --no-interaction --prefer-dist --optimize-autoloader
./vendor/bin/sail dep deploy production
```

## 5. Post-Deploy Checks

```bash
curl -fsSL https://app.gaeld.ch/up
```

Then verify in app:

- Login and organization switch
- Invoice create/finalize/payment flow
- Expense create/approve/post flow
- Reconciliation import flow
- Dashboard loads without server errors

## 6. Rollback

Use Deployer rollback if production checks fail:

```bash
./vendor/bin/sail dep rollback production
```

Then re-run health check and investigate logs.
