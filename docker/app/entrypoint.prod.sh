#!/bin/sh

set -eu

APP_DIR=/var/www/html

required_variables="APP_KEY APP_URL DB_PASSWORD REDIS_PASSWORD AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_BUCKET MAIL_HOST MAIL_FROM_ADDRESS"

for variable in ${required_variables}; do
    eval "value=\${${variable}:-}"

    if [ -z "${value}" ] || [ "${value}" = "null" ]; then
        echo "[entrypoint] HATA: ${variable} üretimde boş olamaz." >&2
        exit 1
    fi
done

if [ "${APP_ENV:-}" != "production" ] || [ "${APP_DEBUG:-}" != "false" ]; then
    echo "[entrypoint] HATA: APP_ENV=production ve APP_DEBUG=false zorunludur." >&2
    exit 1
fi

cd "${APP_DIR}"
php artisan config:cache --no-ansi
php artisan route:cache --no-ansi
php artisan view:cache --no-ansi

exec "$@"
