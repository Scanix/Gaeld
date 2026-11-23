#!/bin/sh
set -eu
stopping=0
trap 'stopping=1' TERM INT
(
    while :; do
        touch /run/gaeld/scheduler
        sleep 30
    done
) &
heartbeat_pid=$!
trap 'kill "$heartbeat_pid" 2>/dev/null || true' EXIT
while [ "$stopping" -eq 0 ]; do
    php artisan schedule:run --no-interaction
    sleep 60 &
    wait "$!" || true
done
