# R1 candidate record — v3.9.0-rc.1

Status: **draft; exact-candidate acceptance pending**. This record does not
authorize stable publication or a customer production installation.

## Identity and scope

| Item | Recorded value |
| --- | --- |
| Baseline | Exact public CE tag `v3.8.28` |
| Candidate branch | `codex/r1-3.9.0-rc.1` |
| Intended candidate tag | `v3.9.0-rc.1`; not created by this record |
| Exact candidate commit | Pending final review and commit |
| Candidate source SHA-256 | Pending artifact build |
| Application image digest | Pending artifact build |
| Web image digest | Pending artifact build |
| PostgreSQL / Redis image digests | Pending artifact build |
| Signed release manifest / provenance / SBOM | Pending release workflow |
| CE/EE boundary contract | `1.0.0`, unchanged |
| Accepted EE pairs | None declared; exact-pair receipt required |
| New database migrations | None |

R1 contains managed Docker and native Linux installation, adoption of a clean
CE v3.8.28 Sail installation, controlled updates, encrypted verified backups,
explicit restoration and operational diagnostics. Dependency security updates
are included in the candidate and must pass its own locked-dependency audits.

R1 does not include the later archive-package, original-document versioning,
append-only conservation audit, PDF/A, signature/timestamp, protected-storage or
conservation supervision features. Existing v3.8.28 accounting and archive
features remain at the baseline. No GeBüV compliance certification is asserted.

## R1 change inventory

The scoped candidate consists of the following files. Review any additional
change against the R1 scope before committing it. Record the finalized artifact
identities in the acceptance evidence rather than treating this source inventory
as a completed release manifest.

| Area | Files |
| --- | --- |
| Managed CLI and diagnostics | `gaeld`; `app/Console/Commands/GaeldDoctorCommand.php`, `GaeldInstallCommand.php`, `GaeldUpdateCommand.php`, `GaeldDatabaseSnapshotCommand.php`, `GaeldRedisSnapshotCommand.php` |
| Operation configuration | `config/distribution.php`, `config/backup.php`; `docker/php/entrypoint.sh` |
| Host installation/adoption | `scripts/distribution/manage.py`, `adopt.py`, `release_manifest.py`, `verify_adoption_receipt.py` |
| Production containers | `docker/production/Dockerfile`, `compose.yml`, `entrypoint.sh`, `scheduler.sh`, `zz-gaeld.conf`, `nginx.conf` |
| Native Linux services | `docker/production/fpm.conf`, `nginx-native.conf`, `gaeld-fpm.service`, `gaeld-web.service`, `gaeld-horizon.service`, `gaeld-scheduler.service` |
| Build and dependency security | `.dockerignore`, `.gitignore`, `composer.lock`, `package.json`, `pnpm-lock.yaml`, `pnpm-workspace.yaml`, `vite.config.js` |
| CI and publication | `.github/workflows/ci.yml`, `.github/workflows/distribution.yml` |
| PHP operation tests | `tests/Unit/Commands/GaeldDoctorCommandTest.php`, `GaeldInstallCommandTest.php`, `GaeldUpdateCommandTest.php`, `GaeldRedisSnapshotCommandTest.php` |
| Distribution tests | `tests/distribution/test_adopt.py`, `test_manager.py`, `test_postgres_recovery.py`, `test_release_manifest.py`, `test_adoption_receipt.py`, `runtime_smoke.py`, `adoption_rehearsal.py`, `adoption_fixture.php` |
| Operator documentation | `README.md`, `CHANGELOG.md`, `RELEASE.md`, `docs/ce-managed-installation.md`, this candidate record |

`composer.json` retains the v3.8.28 direct dependency set. The finalized lockfile
must preserve the selected security updates without adding the later AWS SDK
dependency. The source and image inventories must exclude the R2/R3 provider,
jobs, models, migrations, verifier, PDF/A tools and conservation documentation.
This candidate evidence record remains in Git and is excluded from the image
and its exported source artifact, so recording image hashes cannot change the
image being described. The operator installation guide is included in artifacts.

Initial working-tree scoping checks found no changes to `app/Domains`,
`database/migrations`, `bootstrap/providers.php`, application routes, Horizon
configuration, frontend pages, translations or the CE/EE contract. Shell syntax
and CLI help checks passed. These are preparation checks; repeat the applicable
checks against the final commit and exported artifacts.

## Acceptance evidence

Record the exact commit/artifact digest, command or procedure, date, environment,
result and retained report for every completed item. A pending item must remain
pending until observed on this candidate. Results from the combined R1/R2/R3
development workspace are not candidate acceptance evidence.

| Check | Status | Required evidence |
| --- | --- | --- |
| R1 scope isolation | Pending | No migration, domain, UI or archive-provider changes; no R2/R3 artifacts or runtime dependencies |
| PHP suite and static analysis | Pending | Candidate results, assertion and skip counts, justified skips, PHPStan and formatter output |
| Frontend and dependency security | Pending | Frozen-lockfile build; Composer and pnpm audit results |
| Distribution failure tests | Pending | Signature/hash rejection, incomplete backup, interrupted update/recovery, release version ordering |
| Production image startup | Pending | Candidate app/web image digests; administrator creation and login; PostgreSQL, Redis, Horizon, scheduler, doctor and graceful stop |
| Complete Sail adoption | Pending | Populated exact-v3.8.28 checkout → separate managed candidate; preserved UUIDs, amounts, documents/hashes, encrypted values, APP_KEY and Redis work |
| Source safety after adoption | Pending | Original checkout and volumes retained and stopped; no concurrent writable copies |
| Adoption interruption and retry | Pending | Recorded failed state; preserved original and destination data; explicit acknowledgement when retry could replace new writes |
| Real encrypted restoration | Pending | Actual PostgreSQL/files/Redis/configuration/key recovery; complete hash comparison and application readback |
| Candidate update/recovery | Pending | Exact allowed predecessor; RC ordering; backup before migration; stopped recovery after failure |
| Native Linux source installation | Pending | Clean supported host, locked dependency build, systemd services, permissions, login and recovery |
| Linux AMD64 and ARM64 Docker | Pending | Architecture-specific startup/recovery evidence; distinguish emulation from real-host acceptance |
| Mac Apple Silicon | Pending | Real Docker Desktop host, restart/login, FileVault availability, volume access and backup operation |
| Public artifact boundaries | Pending | Source tar/image audit, no private EE code/secrets/local data, safe extraction and required launcher/docs inventory |
| CE/EE compatibility and staging | Pending | Exact candidate/EE pair receipt and staging results before declaring compatibility |

### Observed working-tree checks

These checks were observed during R1 preparation on 4 November 2026, before
the final commit and signed artifacts. They support review of the scoped
working tree; they are not an acceptance claim for a later tag or rebuilt image.
Retain the local logs and attach the corresponding final-commit results before
closing the acceptance rows above.

| Preparation check | Observed result | Local evidence |
| --- | --- | --- |
| Frontend build with frozen dependencies | Passed; Vite production build completed | `gaeld-r1-candidate-build.log` |
| Global PHPStan scan | Passed; no errors | `gaeld-r1-candidate-phpstan.log` |
| Separate strict console-command scan | Passed; no errors | `gaeld-r1-candidate-commands-phpstan.log` |
| PHP formatting | Passed on the 11 selected changed PHP files; explicit file list used because the container could not access the worktree Git directory for `--dirty` | `gaeld-r1-candidate-pint.log` |
| Composer strict manifest validation | Passed | `gaeld-r1-candidate-composer-validate.log` |
| pnpm production-dependency audit | Passed with `--prod`; no known vulnerabilities reported. This result does not cover development dependencies | `gaeld-r1-candidate-node-audit.log` |
| pnpm complete dependency audit | Passed; no known vulnerabilities reported for the complete dependency set | `gaeld-r1-candidate-node-audit-full.log` |
| Composer dependency audit | Passed on retry; no security advisories. Three abandoned development dependencies remain reported: `dragon-code/contracts`, `dragon-code/pretty-array`, `dragon-code/support` | `gaeld-r1-candidate-composer-audit-retry.log` |
| Distribution unit/failure tests | 29 tests executed: 28 passed, one PostgreSQL restoration test skipped because its isolated opt-in environment was not selected; includes the adoption-receipt gate and failed-backup resumption tests | Local `python3 -m unittest discover -s tests/distribution -v` run |
| PHP application suite | In progress; result not recorded yet | Pending |

The distribution tests exercised real release-signature and backup-encryption
checks, with process fakes for several operational paths. The skipped PostgreSQL
test and complete real Sail adoption still require their own execution evidence.
An earlier Composer audit attempt failed because of DNS resolution; only the
successful retry establishes the recorded advisory result.

## Adoption and recovery dossier

Retain the old commit, resolved Compose configuration and container identities,
source data inventory, encrypted backup receipt and verified hashes. Record the
new managed project, candidate image digests, migration output, application
checks and comparison of preserved records. Keep secrets and recovery identities
outside public reports. Verify that the original installation is stopped and
document the explicit fallback and any writes requiring reconciliation.

For restoration, identify the backup, target environment and operator; restore
the coherent database/files/Redis/configuration/key set and compare the results
with the pre-backup inventory. A mocked subprocess result or successful archive
decryption alone does not demonstrate complete adoption or usable restoration.

## Publication decision

The release workflow creates immutable images and a signed manifest tied to the
reviewed tag, with source artifact, provenance and dependency inventory. Preserve
the independently trusted signing-key fingerprint and exact permitted upgrade
paths. Keep the release as a draft until its required checks are reviewed.

The Dockerfile bases and frontend, CI service images and twelve external actions
are pinned to digests or commit SHAs reviewed on 4 November 2026. The publication
workflow first pushes uniquely named candidate images, exports their exact
source bytes, runs the real Sail adoption/restoration/login rehearsal on those
digests, and validates the resulting receipt before signing the release manifest.
The manifest records the receipt URL and hash; the workflow retains the receipt
as an artifact for 90 days. Tested application/source bytes are not rebuilt
between rehearsal and signing. These controls are implemented; their successful
execution for this candidate remains pending until the workflow evidence exists.

CI adoption runs on Linux AMD64 and does not establish real ARM64/Mac or private
CE/EE acceptance. Package-manager repositories, generated archive dates and
runner tooling are not fully pinned, so this release does not promise that a
later rebuild will reproduce identical bytes. No external release or image was
published by preparing this record.

Stable promotion and any declared CE/EE pairing require their own recorded
acceptance. Enter the reviewer, decision date, approved scope and remaining
limitations here after review. **Decision: pending.**

## Additional precommit verification

The isolated R1 PHP suite completed with 1,595 passing tests, 25 intentional
skips and one failing URL-validation test (6,013 assertions). That existing test
depended on public DNS; its eight-test class passed immediately on retry. Its
valid URL fixture now uses a literal public IP, with no production-code change.
The final full-suite rerun and exact-commit adoption evidence are recorded in
the external acceptance dossier so that reports do not change the tested image.

The five R1 commands also passed a separate PHPStan level-7 scan without the
repository's normal Console exclusion. All dependency audits are clean of
known vulnerability advisories; three abandoned development packages are
reported separately. No legal preservation features from R2/R3 are included.
