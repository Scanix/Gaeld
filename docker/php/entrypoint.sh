#!/bin/sh
set -e

# Auto-install PHP dependencies on first run
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php not found — running composer install..."
    composer install --no-interaction --prefer-dist
fi

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "APP_KEY not set — generating..."
    php artisan key:generate --force
fi

exec "$@"
