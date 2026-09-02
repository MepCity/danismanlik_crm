#!/bin/sh

set -eu

APP_DIR=/var/www/html

# 1. Ortam ve Debug Kontrolü (Yalnız production ve staging kabul edilir; APP_DEBUG=false zorunludur)
case "${APP_ENV:-}" in
    production|staging)
        ;;
    *)
        echo "[entrypoint] HATA: APP_ENV yalnızca 'production' veya 'staging' olabilir. Verilen: '${APP_ENV:-}'" >&2
        exit 1
        ;;
esac

if [ "${APP_DEBUG:-}" != "false" ]; then
    echo "[entrypoint] HATA: Güvenlik gereği production ve staging ortamlarında APP_DEBUG=false zorunludur. Verilen: '${APP_DEBUG:-}'" >&2
    exit 1
fi

# 2. Zorunlu Sırların Kontrolü (Fail-fast)
required_variables="APP_KEY APP_URL DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_BUCKET MAIL_HOST MAIL_FROM_ADDRESS"

for variable in ${required_variables}; do
    eval "value=\${${variable}:-}"

    if [ -z "${value}" ] || [ "${value}" = "null" ]; then
        echo "[entrypoint] HATA: Zorunlu çevre değişkeni boş olamaz: ${variable}" >&2
        exit 1
    fi
done

if [ "${ENTRYPOINT_SKIP_CACHE:-0}" != "1" ]; then
    cd "${APP_DIR}"
    php artisan config:cache --no-ansi
    php artisan route:cache --no-ansi
    php artisan view:cache --no-ansi
fi

exec "$@"
