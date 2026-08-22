# Release Runbook

This runbook coordinates the public Community Edition (CE), the internal
GitLab CE mirror, and the private Enterprise Edition (EE) plugin. It prepares
release commits and commands locally; it does not push or create tags by
itself.

## Release Matrix

Every production release records both immutable refs before deployment:

```text
CE_VERSION=v3.6.0
EE_VERSION=<approved-private-ee-tag>
```

`CE_VERSION` is the public release tag shared by GitHub and GitLab CE.
`EE_VERSION` is selected and tagged in the private `gaeld-ee` repository; it
must not be inferred from the CE version or copied into the public repository.
The deployment pair and the tested commit SHAs belong in the release record.

## Release Gate

Before promoting a release candidate:

- `develop` is clean, reviewed, and green in CI.
- `CHANGELOG.md`, `README.md`, and `INSTALL.md` describe the candidate.
- The candidate commit contains no EE plugin, `deploy.php`, GitLab CI, or SaaS
  Admin frontend source.
- The candidate is tested from a clean CE checkout, not only from a worktree
  containing ignored or untracked files.
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
git diff --check
```

Run these commands from a clean checkout or worktree. Do not use `git clean`
to remove files from a worktree that contains unreviewed work; inspect and
preserve those files, then create a separate clean checkout for release
validation.

Verify the public boundary before staging:

```bash
for forbidden_path in plugins/gaeld-ee deploy.php .gitlab-ci.yml \
  resources/js/Pages/SaasAdmin resources/js/Components/SaasAdmin; do
  if git ls-files -- "$forbidden_path" "$forbidden_path/**" | grep -q .; then
    printf 'Forbidden public path: %s\n' "$forbidden_path"
    exit 1
  fi
done
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
CE_VERSION=v3.6.0
git fetch --prune origin --tags
git fetch --prune gitlab --tags
git switch main
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
DEPLOY_EE_REF="$EE_VERSION" vendor/bin/sail dep deploy production
```

`DEPLOY_EE_REF` is required conceptually even when a default exists: the
Deployer recipe must clone the immutable EE tag or commit rather than a moving
branch. Verify the deployment output names both the CE release and EE ref.

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
DEPLOY_EE_REF=<known-good-ee-tag> vendor/bin/sail dep rollback production
```

3. Re-run the health and accounting smoke tests, inspect logs, and record the
   failed CE SHA, EE SHA, migration state, and recovery result before starting
   another release.
