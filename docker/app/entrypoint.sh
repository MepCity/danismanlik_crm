#!/bin/sh
# Teşvik CRM — app container giriş noktası.
#
# İlk çalıştırmada .env yoksa örnekten üretir ve uygulama anahtarı oluşturur;
# storage ve bootstrap/cache dizinlerinin app kullanıcısı tarafından yazılabilir
# olmasını sağlar; sonra asıl komutu (php-fpm / artisan queue:work / vb.)
# çalıştırır.

set -e

APP_DIR=/var/www/html

# Geliştirme imajında root olarak giriş yaparız; storage/bootstrap cache'i
# app kullanıcısına ver. Üretim imajında zaten app olarak çalışırız.
if [ "$(id -u)" = "0" ]; then
    chown -R app:app "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
fi

# .env yoksa örneğinden oluştur ve APP_KEY üret. .env repo'da tutulmadığı için
# container her ayağa kalkışında bu adım gerekli olabilir; anahtar env içinde
# kalıcı olur (volume olarak mount edilmediği sürece).
if [ ! -f "${APP_DIR}/.env" ]; then
    echo "[entrypoint] .env bulunamadı, .env.example'den üretiliyor."
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    cd "${APP_DIR}" && php artisan key:generate --ansi
fi

# Asıl komutu çalıştır.
exec "$@"
