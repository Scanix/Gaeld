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
docker compose exec laravel.test php artisan gaeld:install

# Or with demo data:
docker compose exec laravel.test php artisan gaeld:install --demo
```

Visit `http://localhost:8080` to access the application.

### Running Tests

Tests run against the PostgreSQL `testing` database (automatically created by the container on first start). Always run tests **inside** the container:

```bash
docker compose exec laravel.test php artisan test
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
