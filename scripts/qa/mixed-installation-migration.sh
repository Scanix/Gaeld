#!/bin/sh

set -eu

SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)"
API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"
SAIL="$API_ROOT/vendor/bin/sail"

if [ "${EDITION_MIGRATION_ALLOW_DB_RESET:-0}" != '1' ]; then
    printf 'Refusing migration rehearsal: set EDITION_MIGRATION_ALLOW_DB_RESET=1 for the isolated test database.\n' >&2
    exit 2
fi

"$SAIL" artisan test --compact \
    tests/Feature/EditionBoundary/MixedInstallationMigrationTest.php \
    tests/Feature/EditionBoundary/EditionRuntimeModeTest.php
"$SAIL" php vendor/bin/phpunit --configuration phpunit.ee.xml \
    plugins/gaeld-ee/tests/Feature/EditionBoundary/EditionRollbackTest.php \
    --no-coverage

printf 'Mixed-installation migration rehearsal passed on the test database.\n'