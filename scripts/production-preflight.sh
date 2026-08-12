#!/bin/sh

set -eu

environment_file=${1:-.env.production}

if [ ! -f "${environment_file}" ]; then
    echo "HATA: üretim ortam dosyası bulunamadı: ${environment_file}" >&2
    exit 1
fi

get_value() {
    key=$1
    sed -n "s/^${key}=//p" "${environment_file}" | tail -n 1
}

required="APP_KEY APP_URL DB_PASSWORD BACKUP_DB_USERNAME BACKUP_DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION AWS_BUCKET OBJECT_REPLICA_ENDPOINT OBJECT_REPLICA_ACCESS_KEY_ID OBJECT_REPLICA_SECRET_ACCESS_KEY OBJECT_REPLICA_BUCKET MAIL_HOST MAIL_FROM_ADDRESS CLOUDFLARE_TUNNEL_TOKEN BACKUP_RESTIC_REPOSITORY BACKUP_RESTIC_PASSWORD BACKUP_AWS_ACCESS_KEY_ID BACKUP_AWS_SECRET_ACCESS_KEY BACKUP_AWS_DEFAULT_REGION SENTRY_LARAVEL_DSN"

for key in ${required}; do
    value=$(get_value "${key}")

    if [ -z "${value}" ] || [ "${value}" = 'null' ]; then
        echo "HATA: ${key} boş bırakılamaz." >&2
        exit 1
    fi
done

if [ "$(get_value APP_ENV)" != production ] || [ "$(get_value APP_DEBUG)" != false ]; then
    echo "HATA: APP_ENV=production ve APP_DEBUG=false zorunludur." >&2
    exit 1
fi

case "$(get_value APP_URL)" in
    https://?*) ;;
    *) echo "HATA: APP_URL geçerli bir https adresi olmalıdır." >&2; exit 1 ;;
esac

endpoint=$(get_value AWS_ENDPOINT)
case "${endpoint}" in
    *localhost*|*127.0.0.1*|*minio*)
        echo "HATA: üretim belge deposu ofis makinesindeki MinIO olamaz." >&2
        exit 1
        ;;
esac

if [ "$(get_value AWS_ACCESS_KEY_ID)" = "$(get_value OBJECT_REPLICA_ACCESS_KEY_ID)" ]; then
    echo "HATA: birincil ve replikasyon nesne depoları ayrı kimlik bilgisi kullanmalıdır." >&2
    exit 1
fi

if [ "${endpoint}" = "$(get_value OBJECT_REPLICA_ENDPOINT)" ] \
    && [ "$(get_value AWS_BUCKET)" = "$(get_value OBJECT_REPLICA_BUCKET)" ]; then
    echo "HATA: evrak replikasyon hedefi birincil kovadan ayrı olmalıdır." >&2
    exit 1
fi

if grep -En '^[[:space:]]+(ports|network_mode:[[:space:]]*host):' compose.prod.yaml >/dev/null 2>&1; then
    echo "HATA: üretim compose dosyası host portu veya host ağı yayımlıyor." >&2
    exit 1
fi

echo "Üretim ön kontrolü başarılı: sırlar dolu, HTTPS zorunlu, yerel nesne deposu ve host portu yok."
