#!/usr/bin/env bash

set -euo pipefail

readonly SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"
readonly SAIL="$API_ROOT/vendor/bin/sail"

ce_artisan() {
    "$SAIL" exec -T laravel.test env \
        PLUGINS_ENABLED=false \
        FEATURE_SAAS=false \
        VITE_PLUGINS_ENABLED=false \
        php artisan "$@"
}

ce_pnpm() {
    "$SAIL" exec -T laravel.test env \
        PLUGINS_ENABLED=false \
        FEATURE_SAAS=false \
        VITE_PLUGINS_ENABLED=false \
        pnpm "$@"
}

if [[ "${CE_SMOKE_ALLOW_DB_RESET:-0}" != '1' ]]; then
    printf 'Refusing CE smoke run: set CE_SMOKE_ALLOW_DB_RESET=1 for an isolated test database.\n' >&2
    exit 2
fi

cleanup() {
    ce_artisan config:clear >/dev/null 2>&1 || true
    ce_artisan route:clear >/dev/null 2>&1 || true
}

trap cleanup EXIT

ce_artisan migrate:fresh --force
ce_artisan config:cache
ce_artisan route:cache
ce_pnpm run build
ce_artisan test --compact \
    tests/Feature/EditionBoundary/CeStandaloneTest.php \
    tests/Feature/EditionBoundary/CeApiContractTest.php \
    tests/Security/EditionBoundary/CeFailClosedTest.php

printf 'CE standalone smoke test passed.\n'