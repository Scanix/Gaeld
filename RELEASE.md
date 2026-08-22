# Public Release Runbook

This runbook covers releases of the public Community Edition for self-hosted
installations.

## Release Gate

Before tagging a release:

- `develop` is clean, reviewed, and green in CI.
- `CHANGELOG.md` has a dated release section and upgrade notes.
- The public README and INSTALL guide match the current commands and runtime.
- Composer and pnpm audits report no known vulnerabilities.
- Composer may report `laragear/webauthn` as abandoned in favor of
  `laravel/passkeys`; this is a tracked migration item, not an unused code path.
- The full test suite, PHPStan, Pint, and frontend build pass.
- A backup and rollback plan exists for every self-hosted deployment.

Run the checks from the repository root:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer audit --locked
./vendor/bin/sail pnpm audit
./vendor/bin/sail artisan test --compact
./vendor/bin/sail bin pint --dirty --format agent
./vendor/bin/sail bin phpstan analyse --memory-limit=2G
./vendor/bin/sail pnpm run build
```

## Promote And Tag

1. Merge the reviewed release PR from `develop` into public `main`.
2. Update the local refs and verify `main` contains the exact tested commit.
3. Create an annotated semantic-version tag:

```bash
git checkout main
git pull --ff-only origin main
git tag -a vX.Y.Z -m "Gäld vX.Y.Z"
git push origin main --follow-tags
```

## Self-Hosted Deployment

Self-hosters may copy [deploy.php.example](deploy.php.example) to `deploy.php`
and configure `DEPLOY_REPO`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, and
`DEPLOY_BRANCH`. Keep `deploy.php` and server `.env` files out of Git.

```bash
./vendor/bin/sail composer install --no-interaction --prefer-dist --optimize-autoloader
./vendor/bin/sail dep deploy production
```

Verify the health endpoint and one authenticated accounting workflow after the
deployment. Restart the configured Horizon or queue service after publishing.
For releases containing the Community Edition API, also verify token creation,
account-code journal posting, an idempotent retry, and a CAMT.053 import. The
API can be disabled immediately with `FEATURE_API_ACCESS=false` if an
integration issue requires rollback while preserving existing ledger data.

## Rollback

If the health check or accounting smoke test fails:

```bash
./vendor/bin/sail dep rollback production
```

Then re-run the health check, inspect application logs, and record the failure
before attempting another release.
