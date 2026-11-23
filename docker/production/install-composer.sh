#!/bin/sh
set -eu

test -f composer.lock
export COMPOSER_CACHE_DIR=/tmp/gaeld-composer-cache
export COMPOSER_MAX_PARALLEL_HTTP=4

# Retry locked downloads only. Installation scripts run once after the cache is complete.
for attempt in 1 2 3; do
    if composer install --download-only --no-dev --prefer-dist --no-scripts --no-interaction; then
        break
    fi
    if [ "$attempt" -eq 3 ]; then
        exit 1
    fi
    sleep "$((attempt * 10))"
done

exec composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
