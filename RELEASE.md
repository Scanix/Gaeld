# Release Runbook

This runbook coordinates the public Community Edition (CE), the internal
GitLab CE mirror, and the private Enterprise Edition (EE) plugin. It prepares
release commits and commands locally; it does not push or create tags by
itself.

## Release Matrix

Every production release records both immutable refs before deployment:

```text
CE_VERSION=v3.7.5
EE_VERSION=v2.9.18
CE_SHA=51aa424
EE_SHA=8d91d37
```

`CE_VERSION` is the public release tag shared by GitHub and GitLab CE.
`EE_VERSION` is selected and tagged in the private `gaeld-ee` repository; it
must not be inferred from the CE version or copied into the public repository.
The deployment pair and the tested commit SHAs belong in the release record.

The current coordinated production release (2026-09-03) is CE `v3.7.5` at
`51aa424` with EE `v2.9.18` at `8d91d37`. It is deployed as release `255`.

## Release Candidate

The next coordinated candidate contains the CE plugin extension API and the
private EE packaging and boundary checks:

```text
CE_VERSION=v3.8.0
EE_VERSION=v2.9.20
CE_SHA=8bbb618
EE_SHA=3cdfd8d
EE_CONTENT_DIGEST=e49cfb95ca8bbd9c6e60f5e5c5b7459897b60fecdfd5ec3f30ee1fca1d9fd478
```

The candidate must be tested from a clean CE archive and a tagged private EE
artifact before staging deployment. Replace both placeholders with the exact
implementation commit SHAs after the release commits are created.

## Validated Staging Candidate

The offer-alignment candidate was deployed and accepted on staging on
2026-09-03:

```text
STAGING_RELEASE=145
API_REF=v3.7.0
EE_REF=v2.9.2
EE_SHA=a6e5863
```

The staging acceptance run passed 41 checks with zero failures, zero skipped
checks, and no console or request errors. It covered disposable Team signup,
email verification, onboarding, accounting, invoices, expenses, payroll, VAT,
year-end close and reopen, permissions, billing, and Stripe test-clock
lifecycle. The staging health endpoint returned `200` with database and cache
checks healthy.

The staging migration dry run scanned 202 active subscriptions, identified 106
Cloud Free subscriptions and 95 legacy subscriptions to preserve, and
performed 0 repairs. No data was changed.

The CE installation was also verified on 2026-09-02 from a clean archive of
the committed API candidate using `./gaeld setup --demo` in an isolated Docker
Compose project. PostgreSQL, Redis, Meilisearch, Mailpit, the application, and
the worker started successfully; healthchecks passed; migrations, admin
organization creation, Swiss chart seeding, demo data, and configuration cache
completed successfully. The temporary project was removed after the check.

The latest production PostgreSQL backup was integrity-checked and restored on
2026-09-03 into a temporary local PostgreSQL database. The restore produced 80
tables, 34 users, 37 organizations, and 52 invoices; the temporary database
was removed and its absence verified afterward. The latest production file
archive also passed gzip integrity checking without extraction.

The CE release was promoted to the public CE `main` branch and the private
`production` branch. Production release `255` was deployed on 2026-09-03 with
the immutable pair above. The offer-alignment and document-storage migrations
are applied, the health endpoint reports healthy database and cache checks,
the failed-job queue is empty, and the offer-plan migration and Stripe price
synchronization dry runs made no changes. The plan-change proration preview was
verified against the live Solo price without mutating the subscription, and
the Billing UI now sends both Laravel CSRF token forms for browser requests.

## Release Gate

Before promoting a release candidate:

- `develop` is clean, reviewed, and green in CI.
- `CHANGELOG.md`, `README.md`, and `INSTALL.md` describe the candidate.
- The candidate commit contains no EE plugin, `deploy.php`, GitLab CI, or SaaS
  Admin frontend source.
- The candidate is tested from a clean CE checkout, not only from a worktree
  containing ignored or untracked files.
- The clean CE archive passes `./scripts/qa/check-ce-artifact.sh` and contains
  no private EE source, populated credentials, commercial source maps, or
  deployment-only files.
- An EE deployment consumes an immutable package from the private GitLab
  Composer registry, verifies its normalized content digest before activation,
  and records the checked pair with `DEPLOY_EE_VERSION` and
  `DEPLOY_EE_CONTENT_DIGEST`.
- Composer and pnpm audits report no known vulnerabilities. Composer may report
  `laragear/webauthn` as abandoned in favor of `laravel/passkeys`; this is a
  tracked migration item, not an unused code path.
- The full test suite, PHPStan, Pint, frontend build, API contract parse, and
  API smoke test pass.
- The EE candidate has its own private tests and release tag.
- A database backup, monitoring plan, and rollback pair exist before deploy.

Run the checks from the repository root:

```bash
test -z "$(git status --short)"
vendor/bin/sail up -d
vendor/bin/sail composer audit --locked --abandoned=report
vendor/bin/sail pnpm audit
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail bin phpstan analyse --memory-limit=2G
vendor/bin/sail pnpm run build
vendor/bin/sail php -r 'json_decode(file_get_contents("contract/api-contract.json"), true, 512, JSON_THROW_ON_ERROR);'
./scripts/qa/check-ce-artifact.sh /path/to/gaeld-ce.tar.gz
./scripts/qa/check-boundary-projections.sh
git diff --check
```

For a hosted deployment, create a deployment-only Composer `auth.json` through
the secret store, then provide its path and the approved content digest without
putting credentials in the command line:

```bash
DEPLOY_EE_VERSION=v2.9.18 \
DEPLOY_EE_COMPOSER_REGISTRY_URL='https://gitlab.nectoria.com/api/v4/group/nectoria/products/gaeld/-/packages/composer/packages.json' \
DEPLOY_EE_COMPOSER_REGISTRY_DOMAIN='gitlab.nectoria.com' \
DEPLOY_EE_COMPOSER_AUTH_FILE=/secure/secrets/gaeld-ee-composer-auth.json \
DEPLOY_EE_CONTENT_DIGEST='<normalized-content-sha256>' \
dep deploy
```

The Deployer template configures GitLab Composer, installs the exact package
version, verifies the normalized content digest, installs its dependencies, and
runs `edition:verify` before `deploy:publish`. A missing, unauthorized, or
mismatched package stops the deployment before traffic activation.

For a mixed installation, take and verify database/file backups first, run
`edition:migrate --dry-run`, review its redacted schema/configuration summary,
and apply only an explicit target mode with `--force`. The migration records
runtime ownership metadata and does not delete EE tables, CE records, hosted
organizations, subscription rows, prices, Stripe identifiers, or billing
history. Roll back by selecting the last compatible immutable CE/EE pair; do
not use `migrate:rollback` as an operational edition rollback.

## Boundary Feature Validation (2026-09-03)

The CE boundary slice passed with 21 tests and 168 assertions. The EE boundary
slice passed with 7 tests and 37 assertions (one registry-consumer test is
skipped when private credentials are unavailable). The complete CE test suites also
passed when run separately to avoid the combined-run timeout:

```text
Unit: 589 tests, 2084 assertions, 30 existing PHPUnit notices
Security: 136 tests, 190 assertions, 6 skipped tests
Feature: 743 tests, 3156 assertions, 2 existing PHPUnit notices and 7 skipped tests
EE complete suite: 106 tests, 744 assertions, 1 skipped registry-consumer test
```

The following gates passed for this candidate: PHPStan on changed PHP paths,
Pint formatting, the API Vite build, the web Vitest and pricing Playwright
checks, public-offer copy validation, the Next.js production build, the
documentation boundary checker, and the Docusaurus build for EN/FR/DE/IT.
The CE and EE package audits also passed without publishing or deploying an
artifact. The SaaS Admin benchmark now runs with isolated database state while
its child process performs migrations; the parent test no longer holds a
transaction that can deadlock PostgreSQL. The live private registry consumer
check was not run because no registry credentials or published digest were
available in this local environment.

The default `phpunit.xml` intentionally runs CE with plugins and throttling
disabled. To exercise the conditional EE tests and the two skipped test paths,
use the dedicated configuration added for the remediation candidate:

```bash
vendor/bin/sail php vendor/bin/phpunit --configuration phpunit.ee.xml \
  --testsuite "Enterprise Edition" --no-coverage
vendor/bin/sail php vendor/bin/phpunit --configuration phpunit.ee.xml \
  tests/Feature/Billing/RegistrationTest.php \
  tests/Security/Billing/StripeWebhookSecurityTest.php \
  tests/Security/Authorization/VerticalPrivilegeTest.php \
  tests/Security/Api/WebhookSsrfTest.php \
  tests/Security/Auth/AuthBypassTest.php --no-coverage
vendor/bin/sail php vendor/bin/phpunit --configuration phpunit.ee.xml \
  tests/Feature/Api/ApiContractTest.php --filter=rate_limit_headers --no-coverage
```

Do not combine the CE and EE suites: `CeInertiaCompatibilityTest` must fail
when EE is loaded because it proves the CE runtime does not resolve EE services.
Run the normal `vendor/bin/sail artisan test --compact` separately for the CE
baseline, then run the EE configuration above for the private surface.

Run these commands from a clean checkout or worktree. Do not use `git clean`
to remove files from a worktree that contains unreviewed work; inspect and
preserve those files, then create a separate clean checkout for release
validation.

The public boundary can also be checked directly from a clean archive:

```bash
./scripts/qa/check-ce-artifact.sh /path/to/gaeld-ce.tar.gz
```

## API Smoke Test

From a clean CE installation, create a short-lived personal or organization
token in the API token settings and keep the plaintext token out of shell
history and release records. Then verify the unauthenticated info endpoint,
authenticated reference data, journal posting, replay, and a CAMT.053 import
with a disposable fixture:

```bash
BASE_URL=http://localhost:8080
: "${API_TOKEN:?Create a short-lived token before running the smoke test}"
curl --fail "$BASE_URL/api/v1/"
curl --fail -H "Authorization: Bearer $API_TOKEN" \
  "$BASE_URL/api/v1/accounts"
curl --fail -H "Authorization: Bearer $API_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: release-smoke-journal-1' \
  --data @- "$BASE_URL/api/v1/journal-entries" <<'JSON'
{
  "date": "2026-01-15",
  "description": "Release smoke test",
  "reference": "release-smoke-1",
  "lines": [
    {"account_code": "1000", "debit": "10.00", "credit": "0.00"},
    {"account_code": "3200", "debit": "0.00", "credit": "10.00"}
  ]
}
JSON
```

Send the same journal request again and verify the stored response is replayed
without creating a second journal entry. Upload a minimal valid CAMT.053
fixture with the same bearer token and verify the import response, imported
transactions, and organization scope. Delete or revoke the smoke-test token
and fixture afterward.

## Promote CE To Both Forges

1. Merge the reviewed release PR from `develop` into public GitHub `main` and
   wait for the tag-capable CI workflow to pass on the exact merge commit.
2. Fetch both remotes and verify that the release tag does not already exist:

```bash
CE_VERSION=v3.6.5
git fetch --prune origin --tags
git fetch --prune gitlab --tags
EE_SHA=7adaabc
git pull --ff-only origin main
CE_SHA=$(git rev-parse HEAD)
if git show-ref --verify --quiet "refs/tags/$CE_VERSION"; then
  printf 'Local tag already exists: %s\n' "$CE_VERSION"
  exit 1
fi
assert_remote_tag_absent() {
  remote="$1"
  if git ls-remote --exit-code --refs "$remote" "refs/tags/$CE_VERSION" >/dev/null; then
    printf 'Remote tag already exists on %s: %s\n' "$remote" "$CE_VERSION"
    exit 1
  else
    exit_code=$?
    if [ "$exit_code" -ne 2 ]; then
      printf 'Could not inspect tags on %s (exit %s)\n' "$remote" "$exit_code" >&2
      exit 1
    fi
  fi
}
assert_remote_tag_absent origin
assert_remote_tag_absent gitlab
```

3. Create one annotated tag at `CE_SHA`, push it to GitHub, then promote the
   same branch and tag to the GitLab CE mirror. A non-fast-forward rejection
   is a coordination failure and must be investigated, not forced:

```bash
git tag -a "$CE_VERSION" "$CE_SHA" -m "Gäld $CE_VERSION"
git push origin main "$CE_VERSION"
git push gitlab main:main "$CE_VERSION"
```

4. Confirm parity after both pushes:

```bash
git fetch --prune origin --tags
git fetch --prune gitlab --tags
test "$(git rev-parse "refs/tags/$CE_VERSION^{commit}")" = "$CE_SHA"
test "$(git ls-remote origin "refs/tags/$CE_VERSION^{}" | cut -f1)" = "$CE_SHA"
test "$(git ls-remote gitlab "refs/tags/$CE_VERSION^{}" | cut -f1)" = "$CE_SHA"
```

GitLab CE is a promotion/mirror target. Do not add `.gitlab-ci.yml` to the
public CE branch to make GitLab run a private pipeline; the GitHub workflow is
the public CI source and explicitly enforces this boundary.

## Release The Private EE Plugin

In the separate private `gaeld-ee` checkout:

1. Review the EE changelog and set the manifest version to the approved
   `EE_VERSION`.
2. Run the private EE test, static-analysis, and asset checks from that
   repository.
3. Create and push an annotated EE tag only after those checks pass.

```bash
# Set EE_VERSION to the approved private EE tag before running these commands.
: "${EE_VERSION:?Set EE_VERSION to the approved private EE tag}"
cd plugins/gaeld-ee
git fetch --prune origin --tags
git switch main
git pull --ff-only origin main
# Update plugin.json and the EE changelog to the approved EE_VERSION.
EE_SHA=$(git rev-parse HEAD)
git tag -a "$EE_VERSION" "$EE_SHA" -m "Gaeld EE $EE_VERSION"
git push origin main "$EE_VERSION"
```

Record `EE_SHA` and verify that the tag resolves to it before deploying. The
EE repository and tag remain private and must never be copied into the CE
checkout or published on GitHub.

## Promote And Deploy Production

The GitLab `production` branch contains private deployment configuration and
is not the public CE branch. After the CE tag and EE tag are both available,
promote the CE release into that protected branch using the internal GitLab
process, preserving `deploy.php` and other production-only files. Verify the
production branch resolves the CE tag before deployment.

Create the database backup, then deploy the exact pair:

```bash
DEPLOY_EE_REF="$EE_VERSION" vendor/bin/dep deploy production
```

`DEPLOY_EE_REF` is required conceptually even when a default exists: the
Deployer recipe must clone the immutable EE tag or commit rather than a moving
branch. Verify the deployment output names both the CE release and EE ref.
The Deployer binary runs from the release checkout; it is not exposed as a Sail
service command in this repository.

After publishing, verify the application health endpoint, login, one existing
accounting workflow, API token authentication, account-code journal posting,
idempotent replay, and the CAMT.053 import. Restart the configured Horizon or
queue service and watch application, worker, and database error logs.

Create the GitHub, GitLab CE, and private GitLab EE release records only after
the corresponding tag and CI result are visible. Link each record to its tag,
tested commit SHA, release notes, migration notes, and the coordinated other
edition.

## Rollback

Never move or delete a published tag. If production validation fails:

1. Disable API access with `FEATURE_API_ACCESS=false` if the issue is isolated
   to integrations and existing ledger workflows must remain available.
2. Roll back the application and EE plugin as a tested pair, using the prior
   CE deployment and its matching immutable `DEPLOY_EE_REF`:

```bash
DEPLOY_EE_REF=<known-good-ee-tag> vendor/bin/dep rollback production
```

3. Re-run the health and accounting smoke tests, inspect logs, and record the
   failed CE SHA, EE SHA, migration state, and recovery result before starting
   another release.

## Backup and Search Operations

Production backups are created by system-level cron jobs rather than the
Laravel scheduler:

- MySQL dumps at 02:00 UTC.
- PostgreSQL dumps and global roles at 02:15 UTC.
- File archives at 02:30 UTC.
- Off-site synchronization and retention at 04:00 UTC.

The shared [scripts/backup-sync.sh](scripts/backup-sync.sh) script copies and
verifies each category before pruning remote archives. Its default retention is
7 days for daily archives and 56 days for weekly archives. The remote must be
provided by the host environment; do not commit a provider path or credentials:

```bash
RCLONE_REMOTE=<configured-backup-remote> \
  /data/backups/scripts/backup-sync.sh
```

Preview a cleanup before applying it:

```bash
DRY_RUN=true RCLONE_REMOTE=<configured-backup-remote> \
  /data/backups/scripts/backup-sync.sh
```

The script refuses to prune a category without a recent local archive and
uses a lock to prevent concurrent runs. For OneDrive, production enables
`ONEDRIVE_HARD_DELETE=true` so expired backup objects do not accumulate in the
recycle bin. Do not run `rclone cleanup` on the account root: it has a broader
scope than the backup directories and can permanently remove unrelated files.

Production search uses Scout with Meilisearch and queued synchronization. A
deployment syncs index settings but does not import all existing records. If
index counts do not match active database records, run the import from the
active release:

```bash
cd /data/www/gaeld_app/current
/usr/bin/php artisan gaeld:meilisearch:reindex
```

Use `--flush` only for a confirmed stale index. Without a model argument, it
rebuilds the Meilisearch documents for `invoices`, `contacts`, and `expenses`;
it does not delete SQL rows. Pass `invoices`, `contacts`, or `expenses` to
limit the rebuild to one index. Verify the index document counts and an
organization-filtered search after the command completes.
