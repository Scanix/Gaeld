#!/usr/bin/env bash

set -euo pipefail

readonly SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"
readonly SAIL="$API_ROOT/vendor/bin/sail"

if [[ "${CE_SMOKE_ALLOW_DB_RESET:-0}" != '1' ]]; then
    printf 'Refusing CE smoke run: set CE_SMOKE_ALLOW_DB_RESET=1 for an isolated test database.\n' >&2
    exit 2
fi

cleanup() {
    "$SAIL" artisan config:clear >/dev/null 2>&1 || true
    "$SAIL" artisan route:clear >/dev/null 2>&1 || true
}

trap cleanup EXIT

export PLUGINS_ENABLED=false
export FEATURE_SAAS=false
export VITE_PLUGINS_ENABLED=false

"$SAIL" artisan migrate:fresh --force
"$SAIL" artisan config:cache
"$SAIL" artisan route:cache
"$SAIL" pnpm run build
"$SAIL" artisan test --compact \
    tests/Feature/EditionBoundary/CeStandaloneTest.php \
    tests/Feature/EditionBoundary/CeApiContractTest.php \
    tests/Security/EditionBoundary/CeFailClosedTest.php

printf 'CE standalone smoke test passed.\n'