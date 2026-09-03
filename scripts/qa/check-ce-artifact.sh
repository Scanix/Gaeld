#!/usr/bin/env bash

set -euo pipefail

readonly SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly API_ROOT="$(CDPATH='' cd -- "$SCRIPT_DIR/../.." && pwd)"

target="${1:-$API_ROOT}"
temporary_directory=''
check_root=''
failures=0

cleanup() {
    if [[ -n "$temporary_directory" ]]; then
        rm -rf "$temporary_directory"
    fi
}

trap cleanup EXIT

fail() {
    printf 'CE artifact violation: %s\n' "$1" >&2
    failures=$((failures + 1))
}

resolve_archive_root() {
    local candidate

    if [[ -f "$1/composer.json" ]]; then
        check_root="$1"
        return
    fi

    while IFS= read -r -d '' candidate; do
        if [[ -f "$candidate/composer.json" ]]; then
            check_root="$candidate"
            return
        fi
    done < <(find "$1" -mindepth 1 -maxdepth 2 -type d -print0)

    fail 'could not find composer.json in extracted artifact'
}

prepare_target() {
    case "$target" in
        *.tar|*.tar.gz|*.tgz)
            temporary_directory="$(mktemp -d)"
            tar -xf "$target" -C "$temporary_directory"
            resolve_archive_root "$temporary_directory"
            ;;
        *.zip)
            temporary_directory="$(mktemp -d)"
            unzip -q "$target" -d "$temporary_directory"
            resolve_archive_root "$temporary_directory"
            ;;
        *)
            if [[ ! -d "$target" ]]; then
                fail "target is neither a directory nor a supported archive: $target"
                return
            fi

            check_root="$(CDPATH='' cd -- "$target" && pwd)"
            ;;
    esac
}

prepare_target

if [[ -z "$check_root" ]]; then
    exit 1
fi

for forbidden_path in \
    'plugins/gaeld-ee' \
    'deploy.php' \
    '.gitlab-ci.yml' \
    'resources/js/Pages/SaasAdmin' \
    'resources/js/Components/SaasAdmin' \
    '.env.production' \
    'auth.json' \
    '.composer/auth.json'; do
    if [[ -e "$check_root/$forbidden_path" ]]; then
        fail "private or deployment-only path is present: $forbidden_path"
    fi
done

credential_token_pattern='(sk_(live|test)_[[:alnum:]_.-]+|whsec_[[:alnum:]_.-]+|gh[pousr]_[[:alnum:]_.-]+|glpat-[[:alnum:]_.-]+|npm_[[:alnum:]_.-]+|-----BEGIN (RSA|OPENSSH|EC) PRIVATE KEY-----|_authToken[[:space:]]*[:=][[:space:]]*[[:punct:]]?[[:alnum:]]{12,})'

has_populated_credential() {
    local file="$1"
    local token
    local tokens

    if ! grep -Iq . "$file"; then
        return 1
    fi

    tokens="$(grep -Eo -- "$credential_token_pattern" "$file" || true)"
    while IFS= read -r token; do
        [[ -z "$token" ]] && continue

        case "$token" in
            *CHANGE_ME*|*change_me*|*replace-me*|*replace_me*|*abc123*|*example*|*placeholder*|*...*)
                ;;
            *)
                return 0
                ;;
        esac
    done <<< "$tokens"

    return 1
}

if [[ -f "$check_root/.npmrc" ]] && has_populated_credential "$check_root/.npmrc"; then
    fail 'registry credentials are present in .npmrc'
fi

while IFS= read -r -d '' file; do
    if has_populated_credential "$file"; then
        fail "secret or registry credential pattern is present in: ${file#"$check_root/"}"
    fi
done < <(
    find "$check_root" \
        \( -path '*/.git' -o -path '*/vendor' -o -path '*/node_modules' -o -path '*/storage' \) -prune \
        -o -type f -print0
)

while IFS= read -r -d '' source_map; do
    if grep -E -q -- '(Plugins\\\\GaeldEE|plugins/gaeld-ee|SaasAdmin|Stripe)' "$source_map"; then
        fail "commercial implementation details are present in source map: ${source_map#"$check_root/"}"
    fi
done < <(find "$check_root" -type f \( -name '*.map' -o -name '*.js.map' \) -print0)

if git -C "$check_root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    untracked_files="$(git -C "$check_root" ls-files --others --exclude-standard)"
    if [[ -n "$untracked_files" ]]; then
        fail "untracked files are present in the release checkout: $untracked_files"
    fi

    while IFS= read -r ignored_file; do
        case "$ignored_file" in
            plugins/gaeld-ee*|deploy.php|.gitlab-ci.yml|.env.production|resources/js/Pages/SaasAdmin*|resources/js/Components/SaasAdmin*)
                fail "ignored private or deployment-only file is present: $ignored_file"
                ;;
        esac
    done < <(git -C "$check_root" status --porcelain --ignored | awk '$1 == "!!" { sub(/^!! /, ""); print }')
fi

if [[ "$failures" -gt 0 ]]; then
    printf '%s CE artifact violation(s) found.\n' "$failures" >&2
    exit 1
fi

printf 'CE artifact audit passed: %s\n' "$check_root"