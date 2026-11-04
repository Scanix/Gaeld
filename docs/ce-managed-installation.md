# Managed CE installation and recovery

The R1 candidate provides managed Docker images and a native Linux installation
built from the signed source artifact. Sail remains the development environment.
The `v3.9.0-rc.1` candidate has a [pending acceptance record](rc-r1-3.9.0-rc.1.md)
and must complete its publication checks before being recommended for production.
Use an isolated installation for candidate validation. R1 adds no accounting
migration or new legal-conservation functionality.

Supported validation targets are Linux AMD64/ARM64 with Docker, Mac Apple
Silicon with Docker, and native Linux with systemd. macOS source checkouts can
build Docker images; native macOS PHP services are not a supported production path.

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
and temporary verification files. Check both host free space and Docker's own
disk allocation. An external backup disk does not expand Docker's internal disk.

Obtain the release verification public key independently from the release
archive, confirm its fingerprint through the project's published trusted
channel, and retain it outside the installation. A public key downloaded next
to an untrusted archive is not an independent trust anchor. Key rotation requires
a separate reviewed operation.

Create an age recovery identity outside the installation, record its recipient,
and store a second copy off-site. The installer verifies that identity and
recipient match. It needs the identity to decrypt and verify recovery backups;
the identity is never included in those backups.

## Install

The `gaeld` entry point and `scripts/distribution` must remain together. Replace
the example version with the exact approved release; floating tags are refused.

```sh
./gaeld install --to 3.9.0-rc.1 --mode docker --root /srv/gaeld \
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

The web listener binds to localhost by default. Configure and validate your
HTTPS reverse proxy or private access solution before granting remote access.
The installer does not publish a server directly to the internet.

## Adopt an existing Sail installation

The initial supported source is the clean CE `v3.8.28` tag. Older installations
must first follow the previous release upgrade procedure to reach that version.
Locally modified installations require an explicit source migration review.

```sh
python3 scripts/distribution/adopt.py \
  --source /existing/gaeld-ce --root /srv/gaeld --to 3.9.0-rc.1 \
  --trust-key /trusted/gaeld-release.pub \
  --backup-recipient AGE_RECIPIENT \
  --identity-file /secure/gaeld-recovery.key
```

Use `--legacy-project` if the original Compose project name is not the inferred
one. The adoption process verifies the old checkout, stops its writers, backs
up and verifies database/files/keys/queues, and transfers into new managed
volumes. It preserves APP_KEY. The original installation and volumes remain
intact and stopped; never run both as writable copies. Follow the recorded
operation state after an interruption. `--resume` explicitly retries adoption
into fresh managed volumes; it does not erase the old installation.
If the failed attempt may already have accepted new writes, resume also requires
`--confirm-replace-new-data` after reconciling those writes. A baseline adoption
backup cannot be restored as an older managed release: retain the original
checkout and volumes as the explicit fallback, and reconcile any new writes
before restarting the original installation.

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
checks services and application health before reopening writes. R1 introduces
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
