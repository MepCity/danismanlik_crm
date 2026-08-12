#!/bin/sh

set -eu
. /usr/local/bin/common.sh

check_once() {
    failed=0
    disk_percent=$(df -P /volumes/pgdata | awk 'NR == 2 {gsub(/%/, "", $5); print $5}')

    if [ "${disk_percent}" -ge "${MONITOR_DISK_PERCENT:-80}" ]; then
        notify_failure "PostgreSQL diski yüzde ${disk_percent} dolu"
        failed=1
    fi

    queue_length=$(redis-cli -h "${REDIS_HOST}" -a "${REDIS_PASSWORD}" --no-auth-warning LLEN queues:default)

    if [ "${queue_length}" -ge "${MONITOR_QUEUE_LENGTH:-100}" ]; then
        notify_failure "kuyruk birikimi ${queue_length} işe ulaştı"
        failed=1
    fi

    next_partition=$(psql -Atqc "SELECT 'audit_log_' || to_char(date_trunc('month', now()) + interval '1 month', 'YYYYMM')")

    if [ "$(psql -Atqc "SELECT to_regclass('public.${next_partition}') IS NOT NULL")" != t ]; then
        notify_failure "gelecek ayın audit_log partition'ı yok: ${next_partition}"
        failed=1
    fi

    latest_backup=$(restic snapshots --tag postgres-base --latest 1 --json | jq -r '.[0].time // empty')

    if [ -z "${latest_backup}" ]; then
        notify_failure "PostgreSQL tam yedeği bulunamadı"
        failed=1
    else
        normalized_backup=$(printf '%s' "${latest_backup}" | sed -E 's/\.[0-9]+Z$/Z/')
        backup_epoch=$(date -u -D '%Y-%m-%dT%H:%M:%SZ' -d "${normalized_backup}" +%s)
        now_epoch=$(date -u +%s)
        age_hours=$(( (now_epoch - backup_epoch) / 3600 ))

        if [ "${age_hours}" -gt "${MONITOR_BACKUP_MAX_AGE_HOURS:-30}" ]; then
            notify_failure "son tam yedek ${age_hours} saat önce alınmış"
            failed=1
        fi
    fi

    if [ ! -s /work/object-replica.last-success ]; then
        notify_failure "başarılı evrak replikasyonu kaydı bulunamadı"
        failed=1
    else
        replica_epoch=$(cat /work/object-replica.last-success)
        now_epoch=${now_epoch:-$(date -u +%s)}
        replica_age_minutes=$(( (now_epoch - replica_epoch) / 60 ))

        if [ "${replica_age_minutes}" -gt "${MONITOR_OBJECT_REPLICA_MAX_AGE_MINUTES:-15}" ]; then
            notify_failure "son evrak replikasyonu ${replica_age_minutes} dakika önce tamamlandı"
            failed=1
        fi
    fi

    return "${failed}"
}

if [ "${1:-}" = "--once" ]; then
    check_once
    exit $?
fi

while true; do
    check_once || true
    sleep "${MONITOR_INTERVAL_SECONDS:-300}"
done
