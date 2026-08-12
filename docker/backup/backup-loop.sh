#!/bin/sh

set -eu
. /usr/local/bin/common.sh

run_locked() {
    script=$1

    if ! flock /work/restic.lock "${script}"; then
        notify_failure "$(basename "${script}") başarısız oldu"
        return 1
    fi
}

full_loop() {
    while true; do
        run_locked /usr/local/bin/backup-full.sh || true
        sleep "${BACKUP_FULL_INTERVAL_SECONDS:-86400}"
    done
}

wal_loop() {
    while true; do
        run_locked /usr/local/bin/backup-wal.sh || true
        sleep "${BACKUP_WAL_INTERVAL_SECONDS:-60}"
    done
}

full_loop &
full_pid=$!
wal_loop &
wal_pid=$!

trap 'kill "${full_pid}" "${wal_pid}" 2>/dev/null || true; wait' TERM INT
wait -n "${full_pid}" "${wal_pid}"
notify_failure "yedekleme döngülerinden biri beklenmedik biçimde durdu"
exit 1
