# Installation Guide

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
| `DOCS_BASE_URL` | Documentation site URL | `http://localhost:3000` |
| `PLUGINS_ENABLED` | Enable plugin system | `true` |
| `TRUSTED_PROXIES` | Trusted reverse proxies (see below) | _(unset)_ |

The public repository is the Community Edition. SaaS billing and Enterprise
features require the private EE plugin and are not installed by this guide.

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
