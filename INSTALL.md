# Installation Guide

## Docker Installation (Recommended)

### Prerequisites

- Docker Engine 24+
- Docker Compose v2+

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Scanix/Gaeld.git
cd Gaeld/api

# 2. Copy environment file
cp .env.example .env

# 3. Start the containers and wait until ready (composer install runs on first start)
docker compose up -d --wait

# 4. Run the installer
./vendor/bin/sail artisan gaeld:install

# Or with demo data:
./vendor/bin/sail artisan gaeld:install --demo
```

Visit `http://localhost:8080` to access the application.

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
- Node.js 20+ and pnpm
- PostgreSQL 15+
- Redis 7+

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/Scanix/Gaeld.git
cd Gaeld/api

# 2. Install PHP dependencies
./vendor/bin/sail composer install

# 3. Install and build frontend
pnpm install
pnpm run build

# 4. Configure environment
cp .env.example .env
./vendor/bin/sail artisan key:generate

# 5. Update .env with your database credentials
# DB_HOST=127.0.0.1
# DB_DATABASE=gaeld
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# 6. Run the installer
./vendor/bin/sail artisan gaeld:install

# Or with demo data:
./vendor/bin/sail artisan gaeld:install --demo

# 7. Start the development server
./vendor/bin/sail up
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
