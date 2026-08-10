# syntax=docker/dockerfile:1
#
# Teşvik CRM — PHP-FPM uygulama imajı
#
# Geliştirme (varsayılan target):  composer dev bağımlılıkları dahil, Xdebug yok,
# opcache geliştirme modunda. Üretim (prod target): --no-dev, opcache üretim
# ayarları, tüm dosyalar app kullanıcısına ait.
#
# UID/GID: build-args USER_ID / GROUP_ID ile host kullanıcısına eşitlenir; böylece
# container'ın storage/bootstrap-cache'e yazdığı dosyalar host'ta root'a ait
# olmaz.

ARG PHP_IMAGE=php:8.4-fpm-bookworm

###############################################################################
# 1. base — paylaşılan katmanlar (sistem + PHP eklentileri + composer)
###############################################################################
FROM ${PHP_IMAGE} AS base

ARG DEBIAN_FRONTEND=noninteractive

# Sistem: PHP eklentilerinin derlemesi için gereken -dev paketleri + git/unzip.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libssl-dev \
        git \
        unzip \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# PHP eklentileri. pdo_pgsql/pgsql: PostgreSQL; intl: UTF-8 + tr-TR; zip: composer;
# gd: imza doğrulama + görüntü; bcmath: parasal hesap; opcache/pcntl: queue:schedule;
# sockets: Redis predis.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        bcmath \
        opcache \
        pcntl \
        sockets

# Redis istemcisi (predis yerine phpredis extension — daha hızlı).
RUN pecl install redis && docker-php-ext-enable redis

# Composer: reski composer imajından binary kopyala (sürüm sabit).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Çalışma dizini.
WORKDIR /var/www/html

###############################################################################
# 2. dev — geliştirme imajı
###############################################################################
FROM base AS dev

# Host UID/GID'sini build-args ile al; app kullanıcısını bu id'yle oluştur,
# php-fpm worker'ları da bu kullanıcıyla çalışsın. Default 1000:1000.
# macOS'ta host GID genelde düşüktür (staff=20) ve imaj içinde çakışabilir;
# bu yüzden GID/UID zaten varsa yeniden kullan, hata verme.
ARG USER_ID=1000
ARG GROUP_ID=1000

RUN set -eux; \
    if getent group "${GROUP_ID}" >/dev/null; then \
        existing_group="$(getent group "${GROUP_ID}" | cut -d: -f1)"; \
        groupmod -n app "${existing_group}"; \
    else \
        groupadd -g "${GROUP_ID}" app; \
    fi; \
    if id -u "${USER_ID}" >/dev/null 2>&1; then \
        existing_user="$(getent passwd "${USER_ID}" | cut -d: -f1)"; \
        usermod -l app -d /home/app -m "${existing_user}"; \
        usermod -g app app; \
    else \
        useradd -u "${USER_ID}" -g app -m -s /bin/bash app; \
    fi; \
    sed -i -E "s|^user = .*|user = app|" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i -E "s|^group = .*|group = app|" /usr/local/etc/php-fpm.d/www.conf

# Geliştirme opcache ayarları: dosya değişikliği anında yeniden okunur.
COPY docker/app/php/opcache.dev.ini /usr/local/etc/php/conf.d/opcache.ini

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

###############################################################################
# 3. prod — üretim imajı
###############################################################################
FROM base AS prod

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN set -eux; \
    if getent group "${GROUP_ID}" >/dev/null; then \
        existing_group="$(getent group "${GROUP_ID}" | cut -d: -f1)"; \
        groupmod -n app "${existing_group}"; \
    else \
        groupadd -g "${GROUP_ID}" app; \
    fi; \
    if id -u "${USER_ID}" >/dev/null 2>&1; then \
        existing_user="$(getent passwd "${USER_ID}" | cut -d: -f1)"; \
        usermod -l app -d /home/app -m "${existing_user}"; \
        usermod -g app app; \
    else \
        useradd -u "${USER_ID}" -g app -m -s /bin/bash app; \
    fi; \
    sed -i -E "s|^user = .*|user = app|" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i -E "s|^group = .*|group = app|" /usr/local/etc/php-fpm.d/www.conf

# Üretim opcache: validate_timestamps kapalı (dosyalar sabit), memory yüksek.
COPY docker/app/php/opcache.prod.ini /usr/local/etc/php/conf.d/opcache.ini

# Tüm kaynak kodunu app kullanıcısına ait olarak kopyala (mount yok, imaja gömülü).
COPY --chown=app:app . /var/www/html

USER app

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
