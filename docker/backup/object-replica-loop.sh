#!/bin/sh

set -eu
. /usr/local/bin/common.sh

replicate_once() (
    require_variable AWS_ACCESS_KEY_ID
    require_variable AWS_SECRET_ACCESS_KEY
    require_variable AWS_DEFAULT_REGION
    require_variable AWS_BUCKET
    require_variable OBJECT_REPLICA_ENDPOINT
    require_variable OBJECT_REPLICA_ACCESS_KEY_ID
    require_variable OBJECT_REPLICA_SECRET_ACCESS_KEY
    require_variable OBJECT_REPLICA_BUCKET

    source_endpoint=${AWS_ENDPOINT:-https://s3.${AWS_DEFAULT_REGION}.amazonaws.com}
    config_directory=$(mktemp -d /work/mc-config.XXXXXX)
    trap 'test -n "${config_directory:-}" && test "${config_directory}" != / && rm -rf "${config_directory}"' EXIT
    chmod 0700 "${config_directory}"

    mcli --config-dir "${config_directory}" alias set source "${source_endpoint}" \
        "${AWS_ACCESS_KEY_ID}" "${AWS_SECRET_ACCESS_KEY}" --api S3v4 >/dev/null || exit 1
    mcli --config-dir "${config_directory}" alias set replica "${OBJECT_REPLICA_ENDPOINT}" \
        "${OBJECT_REPLICA_ACCESS_KEY_ID}" "${OBJECT_REPLICA_SECRET_ACCESS_KEY}" --api S3v4 >/dev/null || exit 1

    # Kaynaktaki silmeler hedefe yayılmaz; hedefteki eski sürüm felaket kurtarma
    # amacıyla korunur. Kova versioning/immutability ayarları sağlayıcı tarafındadır.
    mcli --config-dir "${config_directory}" mirror --overwrite \
        "source/${AWS_BUCKET}" "replica/${OBJECT_REPLICA_BUCKET}" || exit 1
    date -u +%s > /work/object-replica.last-success
    echo "[replica] Evrak nesneleri ayrı hedefe kopyalandı: $(date -u +%FT%TZ)"
)

if [ "${1:-}" = "--once" ]; then
    replicate_once
    exit 0
fi

while true; do
    replicate_once || notify_failure "evrak nesne deposu replikasyonu başarısız oldu"
    sleep "${OBJECT_REPLICA_INTERVAL_SECONDS:-300}"
done
