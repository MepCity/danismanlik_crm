#!/bin/sh

set -eu

: "${BACKUP_DB_USERNAME:?BACKUP_DB_USERNAME boş olamaz}"
: "${BACKUP_DB_PASSWORD:?BACKUP_DB_PASSWORD boş olamaz}"

case "${BACKUP_DB_USERNAME}" in
    *[!a-zA-Z0-9_]*)
        echo "BACKUP_DB_USERNAME yalnız harf, sayı ve alt çizgi içerebilir." >&2
        exit 1
        ;;
esac

psql --username "${POSTGRES_USER}" --dbname "${POSTGRES_DB}" \
    --set=backup_user="${BACKUP_DB_USERNAME}" \
    --set=backup_password="${BACKUP_DB_PASSWORD}" <<'SQL'
SELECT format(
    'CREATE ROLE %I WITH REPLICATION LOGIN PASSWORD %L',
    :'backup_user',
    :'backup_password'
) \gexec
SQL

printf 'host replication %s all scram-sha-256\n' "${BACKUP_DB_USERNAME}" >>"${PGDATA}/pg_hba.conf"
