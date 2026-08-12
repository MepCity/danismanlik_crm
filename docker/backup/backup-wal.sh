#!/bin/sh

set -eu
. /usr/local/bin/common.sh

initialize_repository

if ! find /wal-archive -maxdepth 1 -type f ! -name '*.tmp' | grep -q .; then
    echo "[backup] Arşivlenecek yeni WAL yok."
    exit 0
fi

restic backup /wal-archive \
    --exclude='*.tmp' \
    --tag postgres-wal \
    --host tesvik-crm-db

find /wal-archive -maxdepth 1 -type f ! -name '*.tmp' -delete
echo "[backup] WAL arşivi ofis dışı depoya aktarıldı: $(date -u +%FT%TZ)"
