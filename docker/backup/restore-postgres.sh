#!/bin/sh

set -eu
. /usr/local/bin/common.sh

target=${1:-/restore-pgdata}
recovery_target=${2:-}

case "${target}" in
    /restore-pgdata|/restore-pgdata/*) ;;
    *) notify_failure "geri dönüş hedefi güvenli sınırın dışında: ${target}"; exit 1 ;;
esac

if [ ! -d "${target}" ] || [ -n "$(find "${target}" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
    notify_failure "geri dönüş hedefi var olmalı ve boş olmalı: ${target}"
    exit 1
fi

initialize_repository
restore_directory=$(mktemp -d /work/restore.XXXXXX)
trap 'test -n "${restore_directory:-}" && test "${restore_directory}" != / && rm -rf "${restore_directory}"' EXIT

restic restore latest --tag postgres-base --target "${restore_directory}"
base_directory=$(find "${restore_directory}" -type f -name PG_VERSION -exec dirname {} \; | head -n 1)

if [ -z "${base_directory}" ]; then
    notify_failure "tam yedekte PG_VERSION bulunamadı"
    exit 1
fi

cp -a "${base_directory}/." "${target}/"
mkdir -p "${target}/restore-wal"

if restic snapshots --tag postgres-wal --json | jq -e 'length > 0' >/dev/null; then
    wal_directory=$(mktemp -d /work/wal.XXXXXX)
    restic restore latest --tag postgres-wal --target "${wal_directory}"
    find "${wal_directory}" -type f ! -name '*.tmp' -exec cp {} "${target}/restore-wal/" \;
fi

cat >>"${target}/postgresql.auto.conf" <<'EOF'
restore_command = 'cp /var/lib/postgresql/data/restore-wal/%f %p'
EOF

if [ -n "${recovery_target}" ]; then
    printf "recovery_target_time = '%s'\n" "${recovery_target}" >>"${target}/postgresql.auto.conf"
    printf "recovery_target_action = 'promote'\n" >>"${target}/postgresql.auto.conf"
fi

touch "${target}/recovery.signal"
chmod 0700 "${target}"
chown -R 70:70 "${target}"
echo "[restore] PostgreSQL verisi hazırlandı: ${target}"
