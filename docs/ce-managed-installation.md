# Managed CE installation and recovery

Use the v3.11 installer for a fresh CE installation on Linux Docker (AMD64 or ARM64), Apple Silicon Docker or dedicated Ubuntu 24.04 LTS ARM64 with systemd. For an existing CE Sail installation, follow the adoption procedure below and keep a recovery copy. The signed release record identifies the source and image digests and the supported upgrade paths.

## Prepare the host

Install Python 3.10+, OpenSSL 3 and age/age-keygen. Docker mode additionally
requires a running Docker Engine and Compose v2. Native Linux requires PHP 8.4
and the extensions in `composer.json`, Composer 2, Node 24, the pinned pnpm
version, PostgreSQL 16 client/server, Redis 7, nginx, PHP-FPM and systemd.
Run managed Linux installation and recovery operations as root so every
application-owned file can be read and restored. On macOS, use the logged-in
Docker Desktop account. Native Artisan operations run as `www-data`; the
initial protected administrator setup runs with the operator's privileges.

Use a dedicated installation directory and allow enough free space for the
current release, next release, retained previous release, a full recovery backup
and temporary verification files. Native installation refuses existing global
`gaeld-*` systemd services belonging to another installation before collecting
administrator credentials or changing any service. Use a dedicated host for the
public native installer. Check both host free space and Docker's own
disk allocation. An external backup disk does not expand Docker's internal disk.

Download the [managed release public key](https://docs.gaeld.ch/keys/release.pub) from the independently hosted project documentation. Verify its SHA-256 against the fingerprint in your previously trusted copy of the public launcher or through your operator. Keep the verified file outside the installation and pass its path to `--trust-key`. For the November 2026 key, `sha256sum gaeld-release.pub` must print `a8fc25a937ba95210f64d75ae361ce20ed30c828b3712990c958f93224a3653c`. On macOS, use `shasum -a 256 gaeld-release.pub`. The signer for private EE acceptance has its own [public key](https://docs.gaeld.ch/keys/ee-acceptance.pub) and fingerprint `81c9d5e200e21fd0d6f0df020a5be0f97b31215d6237899e29e708fb56bf8f4c`.

Create an age recovery identity outside the installation, record its recipient,
and store a second copy off-site. The installer verifies that identity and
recipient match. It needs the identity to decrypt and verify recovery backups;
the identity is never included in those backups.

## Install

Keep the `gaeld` entry point and `scripts/distribution` together. Set
`GAELD_RELEASE_VERSION` to the exact approved release number, without the
leading `v`, before running either installation or adoption.

```sh
./gaeld install --to "${GAELD_RELEASE_VERSION:?Set GAELD_RELEASE_VERSION}" --mode docker --root /srv/gaeld \
  --trust-key /trusted/gaeld-release.pub \
  --backup-recipient AGE_RECIPIENT \
  --identity-file /secure/gaeld-recovery.key
```

With a terminal, administrator details are collected with a protected password
prompt. Unattended installation requires `--admin-secret-file=/secure/admin.json`.
The JSON object contains `name`, `email`, `password`, `organization`, `currency`
and `locale`. Use permissions 0400 or 0600. Passwords require at least twelve
characters with letters and numbers. The file is mounted read-only only during
installation. A file created by the protected prompt is deleted afterwards;
an operator-supplied file remains the operator's responsibility. No generic
production password is printed.

`setup --to VERSION` is an alias for managed installation. Plain `setup` in a
development checkout retains the Sail development workflow.

Native Linux uses `--mode native` and an `--environment-file` containing the
operator's database/Redis settings and APP_KEY. It builds locked dependencies
and frontend resources in a fresh release directory and installs dedicated
systemd services. It does not update a customized checkout with `git pull`.

The layout separates `releases/VERSION`, the `current` link, `shared/storage`,
`shared/secrets`, `shared/.env`, backups and `.gaeld` operation state. Treat the
environment and provider secret files as sensitive. APP_KEY is generated only
for a genuinely new managed Docker installation. An existing missing key must
be restored, never replaced.

Before updating an existing native v3.11 or v3.12 installation, refresh the operator's
`gaeld` launcher and its accompanying `scripts/distribution` from the verified
corrected release. Run update and recovery with this launcher. It regenerates the
PHP-FPM and nginx configuration, including when restoring an older signed backup.
The native web server connects to `/run/gaeld-fpm.sock` as `www-data` with mode
0600. If an old launcher stops during the health check, use the corrected launcher
to restore the verified pre-update backup, then update again. Run `./gaeld doctor --root /srv/gaeld` to check the installed socket.

The web listener binds to localhost by default. Configure and validate your
HTTPS reverse proxy or private access solution before granting remote access.
The installer does not publish a server directly to the internet.

## Adopt an existing Sail installation

The adoption command recognizes clean, exactly tagged CE Sail installations at v3.8.28, v3.9.0 and v3.10.0. The target's signed manifest must include the installed version in its tested upgrade paths. Keep a copy of the release verification key and recovery identity outside both installations.

```sh
./gaeld adopt \
  --source /existing/gaeld-ce --root /srv/gaeld --to "${GAELD_RELEASE_VERSION:?Set GAELD_RELEASE_VERSION}" \
  --trust-key /trusted/gaeld-release.pub \
  --backup-recipient AGE_RECIPIENT \
  --identity-file /secure/gaeld-recovery.key
```

Use `--legacy-project` if the original Compose project name is not the inferred
one. The adoption process verifies the old checkout, stops its writers, backs
up and verifies database/files/keys/queues, and transfers into new managed
volumes. It preserves APP_KEY. The original tracked source and volumes remain
intact, with its application services stopped. Its PostgreSQL and Redis services
remain available; never run both applications as writable copies. Follow the recorded
operation state after an interruption. `--resume` explicitly retries adoption
into fresh managed volumes; it does not erase the old installation.
If the failed attempt may already have accepted new writes, resume also requires
`--confirm-replace-new-data` after reconciling those writes. A baseline adoption
backup cannot be restored as an older managed release: retain the original
checkout and volumes as the explicit fallback, and reconcile any new writes
before restarting the original installation. The two untracked Laravel
maintenance files (`storage/framework/down` and `storage/framework/maintenance.php`)
are expected while stopped; tracked changes and other untracked files still
require review.

### Migrate an installation unpacked from a public source archive

Obtain the **original** versioned CE archive from the published release assets. Verify its SHA-256 against the published digest using a separate trusted copy of the release record. Keep the archive outside the running source directory. For example, a source installation unpacked from v3.9.0 uses `gaeld-ce-v3.9.0.tar.gz`.

Run the same adoption command with `--source-archive /secure/gaeld-ce-v3.9.0.tar.gz --source-sha256 VERIFIED_SHA256`. The installer reads the archive's version and source commit, compares each packaged application file with the running copy, checks the Sail services and backs up the original database, files, environment and queues before transfer. Keep both installations and the recovery key until a restore has been tested on a separate system. If a packaged file has changed, reconcile it against the original release in an isolated copy before attempting adoption again.

### Return to the retained Sail installation

Keep the managed application's web, worker and scheduler services stopped, and
reconcile any writes accepted by it before returning to the original database.
Preserve its volumes, files and recovery evidence. A fallback does not merge
changes from the two databases.

The original v3.8.28 Sail entrypoint can regenerate APP_KEY on container startup
when it is absent from the container environment, even when `.env` already has
a key. **Do not simply restart the old containers.** First verify the retained
`.env` against the original environment fingerprint in the adoption record, or
recover the correct original environment from its verified backup. Never create
a new key to make an existing database start.

From the managed launcher directory, create a protected override outside both
installations. This reads the original key without printing it or evaluating
the environment file as shell code. The destination must not already exist;
replace the example paths with the recorded source and a secure directory.

```sh
umask 077
python3 - /existing/gaeld-ce/.env /secure/sail-recovery.override.json <<'PY'
import json
import os
from pathlib import Path
import sys

sys.path.insert(0, str(Path('scripts/distribution').resolve()))
from manage import env_values, valid_application_key

key = env_values(Path(sys.argv[1])).get('APP_KEY', '')
if not valid_application_key(key):
    raise SystemExit('Recover the original valid application key before fallback.')
with Path(sys.argv[2]).open('x') as output:
    os.chmod(sys.argv[2], 0o600)
    json.dump({'services': {
        service: {'environment': {'APP_KEY': key}}
        for service in ('laravel.test', 'worker')
    }}, output)
PY
```

Use the original project name recorded during adoption. Supply both Compose
files on every subsequent start; avoid `docker compose config` output because
it would expose the injected key. Start the retained database and Redis first,
then recreate the application services with the explicit original key:

```sh
docker compose --project-name ORIGINAL_PROJECT --env-file /existing/gaeld-ce/.env \
  -f /existing/gaeld-ce/docker-compose.yml -f /secure/sail-recovery.override.json \
  up -d --no-build --no-deps pgsql redis
docker compose --project-name ORIGINAL_PROJECT --env-file /existing/gaeld-ce/.env \
  -f /existing/gaeld-ce/docker-compose.yml -f /secure/sail-recovery.override.json \
  up -d --no-build --no-deps laravel.test worker
docker compose --project-name ORIGINAL_PROJECT --env-file /existing/gaeld-ce/.env \
  -f /existing/gaeld-ce/docker-compose.yml -f /secure/sail-recovery.override.json \
  exec -T laravel.test php artisan gaeld:doctor
```

Only after those checks and reconciliation, use the same Compose arguments to
run `exec -T laravel.test php artisan up` and reopen the original application.
Retain the protected override with the recovery procedure for any later restart.
The failed managed installation remains stopped until explicitly recovered.

## Update and diagnose

```sh
./gaeld doctor --root /srv/gaeld
./gaeld update --root /srv/gaeld --to APPROVED_NEXT_VERSION
```

Replace `APPROVED_NEXT_VERSION` with an exact published version whose signed
manifest permits your current version. Release manifests may record exact
tested CE/EE pairs backed by signed acceptance receipts. The public installer
installs CE images only; EE installations retain their private paired deployment
workflow. The public updater refuses to replace an EE installation with CE.

The updater verifies the signed manifest and artifact hashes, prepares the next
release, locks the operation, enters maintenance, stops scheduled triggers and
drains active workers. It creates and decrypts a coherent encrypted recovery
backup and verifies its contents before running forward migrations. It then
checks services and application health before reopening writes. The managed installer introduces
no accounting-data backfill or new schema migration.

Any failed stage returns non-zero status and leaves an operation record for
diagnosis. Do not bypass that state with an in-place deployment or an unreviewed
database rollback. No application update or migration runs on its own.

## Backup and restore

```sh
./gaeld backup --root /srv/gaeld
./gaeld restore --root /srv/gaeld \
  --archive /backups/RECOVERY_ARCHIVE.age \
  --identity-file /secure/gaeld-recovery.key \
  --confirm-replace-data
```

Backups capture PostgreSQL through the existing Spatie database-dump component,
application files, configuration, keys, provider secrets, release metadata and
the installation's Redis state. Writers are stopped for coherence. Age provides
authenticated encryption; decryption and file-hash verification are mandatory.
Copy only completed backups and their receipts to Time Machine, Proton Drive
or a rotated off-site device.

Database recovery replaces the entire `public` application schema in one SQL
transaction. This also removes tables introduced by a later or partially failed
migration. It verifies the exact writable PostgreSQL 16 database, database and
schema ownership, and absence of other client sessions before replacement. A
database containing other application schemas is refused and requires a separate
reviewed recovery procedure. A SQL failure rolls back the complete schema change,
and application writers remain stopped. Extensions stored in `public` are restored
from the dump. The existing managed profile uses `--no-owner --no-acl`: the
application role owns restored objects; custom role grants require their own
operator procedure.

Restore replaces the coherent database/files/keys/version set. It requires
explicit confirmation because writes made after the backup would be lost.
Retain the exact release and EE artifact needed by that backup. An automatic
`migrate:rollback` is never used. A previous code directory alone is not a
database recovery plan.

## Mac operation

Use a writable user-owned installation directory, for example
`--root "$HOME/Gaeld"`, instead of the Linux `/srv/gaeld` example above. Retain
the installation launcher outside the replaceable release directories and use
the same root for installation, diagnosis, backup, update and recovery.

Enable Docker Desktop launch at login and test a complete restart followed by
login. With FileVault, the Mac must be unlocked before the Docker environment
is available. Keep the machine powered, connected and awake for scheduled jobs.
Confirm the external SSD is mounted and unlocked before expecting Time Machine
to copy completed backups. Test the behaviour after unplugging and reconnecting
the drive and after a power interruption.

Validate the daily backup schedule with the operator's macOS account. Time
Machine does not make a live PostgreSQL volume copy transactionally coherent.
Proton synchronisation is an additional copy of completed backups; check upload
completion, available space and recovery using the separately retained key.

These recovery backups support installation operation. They do not add a legal
archive, cryptographic accounting proof or a general GeBüV compliance claim.
