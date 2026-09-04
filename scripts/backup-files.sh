#!/usr/bin/env bash
set -Eeuo pipefail

# Back up application files and keep a bounded number of local archives.
# The script is installed by the host operator, not by the Laravel deployer.

BACKUP_DIR="${BACKUP_DIR:-/data/backups/files}"
LOG="${LOG:-/data/backups/logs/backup.log}"
RETENTION_COUNT="${RETENTION_COUNT:-5}"
TIMESTAMP="${TIMESTAMP:-$(date +%Y%m%d_%H%M%S)}"
DAY_OF_WEEK="$(date +%u)"
SOURCE_ROOT="${SOURCE_ROOT:-/}"

DAILY_DIR="$BACKUP_DIR/daily"
WEEKLY_DIR="$BACKUP_DIR/weekly"
DUMP_FILE="$DAILY_DIR/files_${TIMESTAMP}.tar.gz"
TEMP_FILE="$DUMP_FILE.part.$$"
ARCHIVE_COMPLETE=false

if ! [[ "$RETENTION_COUNT" =~ ^[1-9][0-9]*$ ]]; then
    printf 'RETENTION_COUNT must be a positive integer\n' >&2
    exit 1
fi

mkdir -p "$DAILY_DIR" "$WEEKLY_DIR" "$(dirname "$LOG")"

log() {
    printf '[%s] %s\n' "$(date)" "$*" >> "$LOG"
}

remove_incomplete_archive() {
    if [[ "$ARCHIVE_COMPLETE" != true ]]; then
        rm -f -- "$TEMP_FILE"
    fi
}

trap remove_incomplete_archive EXIT

rotate_archives() {
    local directory="$1"
    local prefix="$2"
    local keep_count="$3"

    while IFS= read -r archive; do
        rm -f -- "$archive"
        log "LOCAL CLEANUP: removed $archive"
    done < <(
        find "$directory" -maxdepth 1 -type f -name "${prefix}_*.tar.gz" -printf '%T@ %p\n' \
            | sort -rn \
            | tail -n +"$((keep_count + 1))" \
            | cut -d' ' -f2-
    )
}

# Rotate before creating the next archive. If tar runs out of space, it must
# still have enough room to write the current archive and its log entry.
rotate_archives "$DAILY_DIR" files "$((RETENTION_COUNT - 1))"
rotate_archives "$WEEKLY_DIR" files "$RETENTION_COUNT"

tar_status=0
tar -czf "$TEMP_FILE" \
    --exclude='*/vendor' \
    --exclude='*/node_modules' \
    --exclude='*/.git' \
    --exclude='*/releases/[0-9]*' \
    --exclude='*/releases/*/public/build' \
    --exclude='*/shared/storage/logs' \
    --exclude='*/shared/storage/framework' \
    --exclude='*/logs' \
    --exclude='*.tmp' \
    --exclude='.DS_Store' \
    -C "$SOURCE_ROOT" \
    data/www \
    data/uploads \
    data/gitlab \
    data/home \
    2>> "$LOG" || tar_status=$?

# GNU tar returns 1 when a file changes during the read; that is a warning,
# while 2 or higher means the archive cannot be trusted.
if (( tar_status > 1 )); then
    log "Files FAIL: tar exited with status $tar_status"
    exit "$tar_status"
fi

if [[ ! -s "$TEMP_FILE" ]] || ! gzip -t "$TEMP_FILE" 2>> "$LOG"; then
    log 'Files FAIL: archive is empty or gzip validation failed'
    exit 1
fi

mv -- "$TEMP_FILE" "$DUMP_FILE"
ARCHIVE_COMPLETE=true
log "Files OK: $DUMP_FILE ($(du -sh "$DUMP_FILE" | cut -f1))"

if [[ "$DAY_OF_WEEK" -eq 7 ]]; then
    rotate_archives "$WEEKLY_DIR" files "$((RETENTION_COUNT - 1))"
    ln -- "$DUMP_FILE" "$WEEKLY_DIR/files_${TIMESTAMP}.tar.gz"
fi

rotate_archives "$DAILY_DIR" files "$RETENTION_COUNT"
rotate_archives "$WEEKLY_DIR" files "$RETENTION_COUNT"
log 'Files backup complete'