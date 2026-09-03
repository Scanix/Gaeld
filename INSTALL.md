# Installation Guide

The latest public Community Edition release is `v3.8.1`. The coordinated SaaS
CE/EE production pair and its deployment procedure are documented in
[RELEASE.md](RELEASE.md).

## Docker Installation (Recommended)

### Prerequisites

- Docker Engine 24+
- Docker Compose v2+

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Scanix/Gaeld.git
cd Gaeld

# 2. Start Docker Desktop or the Docker Engine
# 3. Build, start, wait for, and install Gäld
./gaeld setup

# Or with demo data:
./gaeld setup --demo
```

Visit `http://localhost:8080` to access the application.

### Troubleshooting and maintenance

Run the diagnostic command from the repository root when login, redirects,
database, cache, storage, or migrations behave unexpectedly:

```bash
./gaeld doctor
```

After pulling a newer release, update an existing installation with:

```bash
./gaeld update
```

`./gaeld setup` creates `.env` when needed, builds the image, starts the
dependencies, waits for PostgreSQL, Redis, and the application health endpoint,
then runs the installer. Docker must already be running: setup checks the
selected Docker context immediately and exits with an actionable error when the
engine is unavailable. It does not install or launch Docker Desktop
automatically.

On Linux, if `docker info` reports `permission denied`, grant the current user
access to the Docker socket and start a new session:

```bash
sudo usermod -aG docker "$USER"
newgrp docker
docker info
```

### Running Tests

Tests run against the PostgreSQL `testing` database (automatically created by the container on first start). Always run tests **inside** the container:

```bash
./vendor/bin/sail artisan test
```

### Default Demo Credentials

After seeding:
- Email: `admin@gaeld.local`
- Password: `password`

---

## Manual Installation

### Prerequisites

- PHP 8.4+
- Composer
- Node.js 22+ and pnpm
- PostgreSQL 15+
- Redis 7+

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Scanix/Gaeld.git
cd Gaeld

# 2. Install PHP dependencies
composer install

# 3. Install and build frontend
pnpm install
pnpm run build

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# 5. Update .env with your database credentials
# DB_HOST=127.0.0.1
# DB_DATABASE=gaeld
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# 6. Run the installer
php artisan gaeld:install

# Or with demo data:
php artisan gaeld:install --demo

# 7. Start the development server
php artisan serve
```

Visit `http://localhost:8000` to access the application.

---

## Environment Configuration

Key environment variables:

| Variable | Description | Default |
|---|---|---|
| `DB_CONNECTION` | Database driver | `pgsql` |
| `CACHE_STORE` | Cache backend | `redis` |
| `QUEUE_CONNECTION` | Queue backend | `redis` |
| `FEATURE_BANK_SYNC` | Enable bank sync | `false` |
| `FEATURE_AUTOMATION` | Enable automation | `false` |
| `FEATURE_API_ACCESS` | Enable the Community Edition REST API | `true` |
| `SCOUT_DRIVER` | Search engine | `database` |
| `SCOUT_QUEUE` | Queue Scout index synchronization | `true` |
| `MEILISEARCH_HOST` | Meilisearch URL when `SCOUT_DRIVER=meilisearch` | `http://localhost:7700` |
| `MEILISEARCH_KEY` | Meilisearch API key when `SCOUT_DRIVER=meilisearch` | _(unset)_ |
| `DOCS_BASE_URL` | Documentation site URL | `http://localhost:3000` |
| `PLUGINS_ENABLED` | Enable optional plugin system | `false` |
| `TRUSTED_PROXIES` | Trusted reverse proxies (see below) | _(unset)_ |

The public repository is the complete Community Edition under
AGPL-3.0-or-later. SaaS billing, hosted quotas, subscriptions, and Enterprise
features belong to the private EE distribution and are not installed or
required by this guide. A self-hosted CE installation does not require a SaaS
subscription or private registry credentials.
The Community Edition REST API is enabled by default and can be disabled with
`FEATURE_API_ACCESS=false` when an installation does not need external access.

---

## Reverse Proxy / HTTPS

When Gäld runs behind a reverse proxy that terminates TLS — for example
Coolify, Traefik, nginx, Caddy, or Cloudflare — you must tell Laravel to
trust the forwarded headers (`X-Forwarded-Proto`, `X-Forwarded-For`,
`X-Forwarded-Host`). Without this, Laravel generates `http://` URLs and
redirects from an HTTPS page, which the browser blocks as mixed content.

Set the `TRUSTED_PROXIES` environment variable:

```bash
# Trust any proxy — safe when the container is only reachable via the proxy
TRUSTED_PROXIES=*

# Or restrict to specific proxy IPs / CIDR ranges for tighter security
TRUSTED_PROXIES=10.0.0.5,172.18.0.0/16
```

Also make sure `APP_URL` uses the public HTTPS URL, e.g.
`APP_URL=https://accounting.example.com`.

## Search / Meilisearch

The default `database` Scout driver needs no additional service. For larger
installations, Meilisearch can be enabled with:

```env
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-search-api-key
```

After changing index settings or deploying a new searchable model, synchronize
the settings and import existing records:

```bash
./vendor/bin/sail artisan scout:sync-index-settings
./vendor/bin/sail artisan gaeld:meilisearch:reindex
```

The reindex command imports `invoices`, `contacts`, and `expenses` without
deleting application data. If an index contains stale documents, add
`--flush` to rebuild only the corresponding Meilisearch indexes, then verify
the document counts and an organization-scoped search.

## Backup Retention

The production deployment uses system-level backup scripts for MySQL,
PostgreSQL, files, and off-site synchronization. The shared synchronization
script is [scripts/backup-sync.sh](scripts/backup-sync.sh); it requires an
explicit `RCLONE_REMOTE` value and keeps 7 days of daily archives and 56 days
of weekly archives by default.

Preview a retention run before applying it:

```bash
DRY_RUN=true RCLONE_REMOTE=<configured-backup-remote> \
	/data/backups/scripts/backup-sync.sh
```

The script verifies the local archive before pruning the remote destination and
prevents concurrent runs. Keep the provider path and credentials outside Git.
For OneDrive, only enable `ONEDRIVE_HARD_DELETE=true` when the remote is known
to be dedicated to these backup directories. Do not use `rclone cleanup` on a
shared account root.

---

## Upgrading

> **Before upgrading:** read the [CHANGELOG](CHANGELOG.md) for breaking
> changes, and back up your database.

### Docker

```bash
# 1. Pull latest code
git pull

# 2. Rebuild the image (picks up PHP/Node changes)
docker compose build

# 3. Restart containers — composer install and pnpm build run automatically
docker compose up -d --wait

# 4. Run migrations
./vendor/bin/sail artisan migrate --force

# 5. Clear compiled caches
./vendor/bin/sail artisan optimize:clear

# 6. Restart queue workers
./vendor/bin/sail artisan horizon:terminate
```

### Manual

```bash
# 1. Pull latest code
git pull

# 2. Update PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Rebuild frontend assets
pnpm install --frozen-lockfile
pnpm run build

# 4. Run migrations
php artisan migrate --force

# 5. Clear compiled caches
php artisan optimize:clear

# 6. Restart queue workers
php artisan horizon:terminate
```
