#!/usr/bin/env bash
set -euo pipefail

RCLONE_REMOTE="${RCLONE_REMOTE:?RCLONE_REMOTE must be set to the backup destination}"
BACKUP_DIR="${BACKUP_DIR:-/data/backups}"
LOG="${LOG:-$BACKUP_DIR/logs/backup.log}"
RCLONE_LOG="${RCLONE_LOG:-$BACKUP_DIR/logs/rclone.log}"
DAILY_RETENTION_DAYS="${DAILY_RETENTION_DAYS:-7}"
WEEKLY_RETENTION_DAYS="${WEEKLY_RETENTION_DAYS:-56}"
LOCAL_DAILY_RETENTION_COUNT="${LOCAL_DAILY_RETENTION_COUNT:-5}"
LOCAL_WEEKLY_RETENTION_COUNT="${LOCAL_WEEKLY_RETENTION_COUNT:-5}"
LOCK_FILE="${LOCK_FILE:-$BACKUP_DIR/.backup-sync.lock}"
ONEDRIVE_HARD_DELETE="${ONEDRIVE_HARD_DELETE:-false}"

RCLONE_OPTIONS=(
    --transfers 4
    --checkers 8
    --log-level NOTICE
    --log-file "$RCLONE_LOG"
)

DRY_RUN="${DRY_RUN:-false}"

if [[ "$DRY_RUN" != "true" && "$DRY_RUN" != "false" ]]; then
    printf 'DRY_RUN must be true or false\n' >&2

    exit 1
fi

if [[ "$ONEDRIVE_HARD_DELETE" != "true" && "$ONEDRIVE_HARD_DELETE" != "false" ]]; then
    printf 'ONEDRIVE_HARD_DELETE must be true or false\n' >&2

    exit 1
fi

if [[ "$ONEDRIVE_HARD_DELETE" == "true" && "$RCLONE_REMOTE" != onedrive:* ]]; then
    printf 'ONEDRIVE_HARD_DELETE=true requires an onedrive remote\n' >&2

    exit 1
fi

if ! [[ "$LOCAL_DAILY_RETENTION_COUNT" =~ ^[1-9][0-9]*$ ]] || ! [[ "$LOCAL_WEEKLY_RETENTION_COUNT" =~ ^[1-9][0-9]*$ ]]; then
    printf 'Local retention counts must be positive integers\n' >&2

    exit 1
fi

log() {
    printf '[%s] %s\n' "$(date)" "$*" >> "$LOG"
}

has_local_archive() {
    local directory="$1"
    local pattern="$2"

    find "$directory" -maxdepth 1 -type f -name "$pattern" -print -quit | grep -q .
}

has_recent_local_archive() {
    local directory="$1"
    local pattern="$2"
    local max_age_minutes=$((DAILY_RETENTION_DAYS * 1440))

    find "$directory" -maxdepth 1 -type f -name "$pattern" \
        -mmin "-${max_age_minutes}" -print -quit | grep -q .
}

prune_local_archives() {
    local directory="$1"
    local pattern="$2"
    local keep_count="$3"

    [[ -d "$directory" ]] || return 0

    while IFS= read -r archive; do
        rm -f -- "$archive"
        log "LOCAL CLEANUP: removed $archive"
    done < <(
        find "$directory" -maxdepth 1 -type f -name "$pattern" -printf '%T@ %p\n' \
            | sort -rn \
            | tail -n +"$((keep_count + 1))" \
            | cut -d' ' -f2-
    )
}

sync_and_prune() {
    local category="$1"
    local pattern="$2"
    local daily_source="$BACKUP_DIR/$category/daily"
    local weekly_source="$BACKUP_DIR/$category/weekly"
    local daily_remote="$RCLONE_REMOTE/$category/daily"
    local weekly_remote="$RCLONE_REMOTE/$category/weekly"

    # Free local space before copying. The creation job keeps the newest five;
    # this also protects the host when an older job failed before its cleanup.
    prune_local_archives "$daily_source" "$pattern" "$LOCAL_DAILY_RETENTION_COUNT"
    prune_local_archives "$weekly_source" "$pattern" "$LOCAL_WEEKLY_RETENTION_COUNT"

    if [[ ! -d "$daily_source" ]] || ! has_local_archive "$daily_source" "$pattern"; then
        log "SYNC FAIL: $category daily has no local archive"

        return 1
    fi

    if ! has_recent_local_archive "$daily_source" "$pattern"; then
        log "SYNC FAIL: $category daily has no recent archive"

        return 1
    fi

    if [[ "$DRY_RUN" == "true" ]]; then
        if ! rclone copy "$daily_source" "$daily_remote" \
            --include "$pattern" \
            "${RCLONE_OPTIONS[@]}" \
            --dry-run \
            2>> "$RCLONE_LOG"; then
            log "SYNC FAIL: $category daily copy"

            return 1
        fi
    else
        if ! rclone copy "$daily_source" "$daily_remote" \
            --include "$pattern" \
            "${RCLONE_OPTIONS[@]}" \
            2>> "$RCLONE_LOG"; then
            log "SYNC FAIL: $category daily copy"

            return 1
        fi

        if ! rclone check "$daily_source" "$daily_remote" \
            --one-way \
            --include "$pattern" \
            "${RCLONE_OPTIONS[@]}" \
            2>> "$RCLONE_LOG"; then
            log "SYNC FAIL: $category daily verification"

            return 1
        fi
    fi

    delete_args=(
        "$daily_remote"
        --min-age "${DAILY_RETENTION_DAYS}d"
        --include "$pattern"
        "${RCLONE_OPTIONS[@]}"
    )
    if [[ "$DRY_RUN" == "true" ]]; then
        delete_args+=(--dry-run)
    fi
    if [[ "$ONEDRIVE_HARD_DELETE" == "true" ]]; then
        delete_args+=(--onedrive-hard-delete)
    fi

    if ! rclone delete "${delete_args[@]}" 2>> "$RCLONE_LOG"; then
        log "SYNC FAIL: $category daily cleanup"

        return 1
    fi

    if [[ -d "$weekly_source" ]] && has_local_archive "$weekly_source" "$pattern"; then
        weekly_copy_args=(
            "$weekly_source"
            "$weekly_remote"
            --include "$pattern"
            "${RCLONE_OPTIONS[@]}"
        )
        if [[ "$DRY_RUN" == "true" ]]; then
            weekly_copy_args+=(--dry-run)
        fi

        if ! rclone copy "${weekly_copy_args[@]}" \
            2>> "$RCLONE_LOG"; then
            log "SYNC FAIL: $category weekly copy"

            return 1
        fi

        if [[ "$DRY_RUN" != "true" ]] && ! rclone check "$weekly_source" "$weekly_remote" \
            --one-way \
            --include "$pattern" \
            "${RCLONE_OPTIONS[@]}" \
            2>> "$RCLONE_LOG"; then
            log "SYNC FAIL: $category weekly verification"

            return 1
        fi

    fi

    delete_args=(
        "$weekly_remote"
        --min-age "${WEEKLY_RETENTION_DAYS}d"
        --include "$pattern"
        "${RCLONE_OPTIONS[@]}"
    )
    if [[ "$DRY_RUN" == "true" ]]; then
        delete_args+=(--dry-run)
    fi
    if [[ "$ONEDRIVE_HARD_DELETE" == "true" ]]; then
        delete_args+=(--onedrive-hard-delete)
    fi

    if ! rclone delete "${delete_args[@]}" 2>> "$RCLONE_LOG"; then
        log "SYNC FAIL: $category weekly cleanup"

        return 1
    fi

    if [[ "$DRY_RUN" == "true" ]]; then
        log "SYNC DRY RUN: $category (daily ${DAILY_RETENTION_DAYS}d, weekly ${WEEKLY_RETENTION_DAYS}d, hard delete ${ONEDRIVE_HARD_DELETE})"
    else
        log "SYNC OK: $category (daily ${DAILY_RETENTION_DAYS}d, weekly ${WEEKLY_RETENTION_DAYS}d, hard delete ${ONEDRIVE_HARD_DELETE})"
    fi
}

mkdir -p "$(dirname "$LOG")" "$(dirname "$RCLONE_LOG")"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    log 'SYNC SKIP: another backup sync is already running'

    exit 1
fi

if ! command -v rclone > /dev/null 2>&1; then
    log 'SYNC FAIL: rclone not installed'

    exit 1
fi

sync_and_prune mysql '*.sql.gz'
sync_and_prune postgresql '*.sql.gz'
sync_and_prune files '*.tar.gz'

log 'OneDrive sync and remote retention complete'