#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY:-}" = 'base64:' ]; then
    echo 'APP_KEY is missing. Recover the original key; container startup never generates or replaces it.' >&2
    exit 1
fi
test -f vendor/autoload.php && test -f public/build/manifest.json || {
    echo 'Incomplete release image.' >&2
    exit 1
}
exec "$@"
