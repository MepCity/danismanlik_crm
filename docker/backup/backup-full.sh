#!/bin/sh

set -eu
. /usr/local/bin/common.sh

require_variable PGHOST
require_variable PGDATABASE
require_variable PGUSER
initialize_repository

work_directory=$(mktemp -d /work/base-backup.XXXXXX)
trap 'test -n "${work_directory:-}" && test "${work_directory}" != / && rm -rf "${work_directory}"' EXIT

mkdir -p "${work_directory}/base"
pg_basebackup \
    --checkpoint=fast \
    --format=plain \
    --pgdata="${work_directory}/base" \
    --wal-method=stream \
    --progress

restic backup "${work_directory}/base" \
    --tag postgres-base \
    --host tesvik-crm-db

restic forget \
    --tag postgres-base \
    --keep-daily "${BACKUP_RETENTION_DAILY:-14}" \
    --keep-monthly "${BACKUP_RETENTION_MONTHLY:-12}" \
    --prune

echo "[backup] PostgreSQL tam yedeği tamamlandı: $(date -u +%FT%TZ)"
