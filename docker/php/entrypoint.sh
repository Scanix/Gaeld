#!/bin/sh
set -e

# Auto-install PHP dependencies on first run
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php not found — running composer install..."
    composer install --no-interaction --prefer-dist
fi

# Auto-install and build frontend on first run
if [ ! -f public/build/manifest.json ]; then
    echo "Vite manifest not found — installing and building frontend..."
    pnpm install --frozen-lockfile
    pnpm run build
fi

# APP_KEY belongs to the installation. Only the explicit first-time setup may
# generate it; worker/web restarts must never overwrite an existing .env key.

exec "$@"
