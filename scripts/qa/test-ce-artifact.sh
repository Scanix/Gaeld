#!/usr/bin/env bash

set -euo pipefail

readonly SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"
readonly TEMPORARY_DIRECTORY="$(mktemp -d)"
readonly FIXTURE_ROOT="$TEMPORARY_DIRECTORY/checkout"

trap 'rm -rf "$TEMPORARY_DIRECTORY"' EXIT

fail() {
    printf 'CE artifact regression failed: %s\n' "$1" >&2
    exit 1
}

assert_audit_passes() {
    if ! bash "$SCRIPT_DIR/check-ce-artifact.sh" "$1" > "$TEMPORARY_DIRECTORY/audit.log" 2>&1; then
        cat "$TEMPORARY_DIRECTORY/audit.log" >&2
        fail "safe target was rejected: $1"
    fi
}

assert_audit_rejects() {
    if bash "$SCRIPT_DIR/check-ce-artifact.sh" "$1" > "$TEMPORARY_DIRECTORY/audit.log" 2>&1; then
        fail "release archive with development files was accepted: $1"
    fi

    if ! grep -Fq -- "must not be present in release archives: $2" "$TEMPORARY_DIRECTORY/audit.log"; then
        cat "$TEMPORARY_DIRECTORY/audit.log" >&2
        fail "missing development-file diagnostic for: $2"
    fi
}

mkdir -p "$FIXTURE_ROOT/app/tests" "$FIXTURE_ROOT/public" "$FIXTURE_ROOT/tests/fixtures/security"
printf '{"name":"gaeld/artifact-regression"}\n' > "$FIXTURE_ROOT/composer.json"
printf '<?php // Runtime entry point.\n' > "$FIXTURE_ROOT/artisan"
printf '<?php // Runtime source.\n' > "$FIXTURE_ROOT/app/Runtime.php"
printf '<?php // A nested runtime directory must remain.\n' > "$FIXTURE_ROOT/app/tests/Runtime.php"
printf '<?php // Public entry point.\n' > "$FIXTURE_ROOT/public/index.php"
printf '<?php // Development test.\n' > "$FIXTURE_ROOT/tests/ExampleTest.php"
printf '<svg><script>alert("fixture")</script></svg>\n' > "$FIXTURE_ROOT/tests/fixtures/security/hostile.svg"
printf '<phpunit/>\n' > "$FIXTURE_ROOT/phpunit.xml"

git -C "$FIXTURE_ROOT" init --quiet
git -C "$FIXTURE_ROOT" add .
git -C "$FIXTURE_ROOT" -c user.name='Artifact regression' -c user.email='artifact-regression@example.invalid' \
    -c commit.gpgsign=false commit --quiet -m 'Fixture without archive exclusions'
readonly UNSAFE_REVISION="$(git -C "$FIXTURE_ROOT" rev-parse HEAD)"

cp "$API_ROOT/.gitattributes" "$FIXTURE_ROOT/.gitattributes"
git -C "$FIXTURE_ROOT" add .gitattributes
git -C "$FIXTURE_ROOT" -c user.name='Artifact regression' -c user.email='artifact-regression@example.invalid' \
    -c commit.gpgsign=false commit --quiet -m 'Use repository archive attributes'

# Source audits must continue accepting tracked tests and their configuration.
assert_audit_passes "$FIXTURE_ROOT"

for format in tar tar.gz zip; do
    for prefix in '' 'gaeld-release/'; do
        label="${format}-${prefix:-root}"
        label="${label//\//}"
        archive="$TEMPORARY_DIRECTORY/safe-$label.$format"
        extracted="$TEMPORARY_DIRECTORY/extracted-$label"

        git -C "$FIXTURE_ROOT" archive --format="$format" --prefix="$prefix" --output="$archive" HEAD
        assert_audit_passes "$archive"

        mkdir "$extracted"
        if [[ "$format" == zip ]]; then
            unzip -q "$archive" -d "$extracted"
        else
            tar -xf "$archive" -C "$extracted"
        fi

        artifact_root="$extracted/${prefix%/}"
        for runtime_path in composer.json artisan app/Runtime.php app/tests/Runtime.php public/index.php; do
            [[ -f "$artifact_root/$runtime_path" ]] || fail "runtime file was excluded: $runtime_path ($label)"
        done

        [[ ! -e "$artifact_root/tests" ]] || fail "test suite was archived: $label"
        [[ ! -e "$artifact_root/phpunit.xml" ]] || fail "test configuration was archived: $label"

        unsafe_archive="$TEMPORARY_DIRECTORY/unsafe-$label.$format"
        git -C "$FIXTURE_ROOT" archive --format="$format" --prefix="$prefix" \
            --output="$unsafe_archive" "$UNSAFE_REVISION"
        assert_audit_rejects "$unsafe_archive" tests
        assert_audit_rejects "$unsafe_archive" phpunit.xml

        # Fixtures alone still need rejection when no test class is included.
        fixture_archive="$TEMPORARY_DIRECTORY/fixture-$label.$format"
        git -C "$FIXTURE_ROOT" archive --format="$format" --prefix="$prefix" \
            --output="$fixture_archive" "$UNSAFE_REVISION" composer.json tests/fixtures/security/hostile.svg
        assert_audit_rejects "$fixture_archive" tests

        configuration_archive="$TEMPORARY_DIRECTORY/configuration-$label.$format"
        git -C "$FIXTURE_ROOT" archive --format="$format" --prefix="$prefix" \
            --output="$configuration_archive" "$UNSAFE_REVISION" composer.json phpunit.xml
        assert_audit_rejects "$configuration_archive" phpunit.xml
    done
done

[[ -f "$FIXTURE_ROOT/tests/ExampleTest.php" ]] || fail 'source checkout lost its test suite'
[[ -f "$FIXTURE_ROOT/tests/fixtures/security/hostile.svg" ]] || fail 'source checkout lost its hostile fixture'
[[ -f "$FIXTURE_ROOT/phpunit.xml" ]] || fail 'source checkout lost its test configuration'

printf 'CE artifact regression passed: archive exclusions, runtime files, source checkout, and rejection diagnostics.\n'
