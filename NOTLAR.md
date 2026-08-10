# WP-01 — Teslim notları

## Ne yapıldı

Docker tabanlı geliştirme ortamı ve Laravel 13.24 iskeleti kuruldu. Host'a
PHP/Composer/PostgreSQL kurulmadı; tüm komutlar container içinde çalışır.

### İmajlar / sürümler

| Bileşen   | Sürüm / imaj                       |
|-----------|------------------------------------|
| PHP       | 8.4.24 (`php:8.4-fpm-bookworm`)    |
| Laravel   | 13.24.0                            |
| PostgreSQL| 17 (`postgres:17-alpine`)          |
| Redis     | 7 (`redis:7-alpine`)               |
| Nginx     | `nginx:stable`                     |
| MinIO     | `minio/minio:latest`               |
| Mailpit   | `axllent/mailpit:latest`           |

### Dosya yapısı

```
docker/
  app/
    Dockerfile          # çok aşamalı: base → dev / prod
    entrypoint.sh       # .env üretir, key:generate, storage sahipliği
    php/opcache.dev.ini
    php/opcache.prod.ini
  web/default.conf      # nginx vhost → app:9000
compose.yaml            # 9 servis
.env.example            # pgsql/redis/minio/mailpit servis adlarıyla dolu
Makefile                # up/down/shell/test/fresh + artisan/composer kısayolları
README.md
```

## Alınan kararlar

1. **Web servisi → Nginx (Caddy değil).** Laravel+PHP-FPM için en olgun ve en
   iyi belgelenmiş yol. Üretimde HTTPS Cloudflare Tunnel'de (K-10) sonlanacağı
   için Caddy'nin otomatik-HTTPS avantajı devre dışı kalır; Nginx'in olgunluğu
   daha az operasyon riski. Geliştirme ve üretim aynı yapı (K-03).

2. **Redis host'a yayınlanmaz.** Uygulama compose ağı üzerinden `redis` servis
   adıyla bağlanır; host portu yalnızca elle hata ayıklama için gerekliydi. Bu
   makinede 6379 hostta yerel `redis-server` kullanımda olduğu için yayın yapmadan
   bırakmak çakışmayı kökten çözer. Gerekirse `.env`'deki `PUBLISH_REDIS_PORT`
   açılıp compose'taki yorum satırı kaldırılır.

3. **UID/GID eşlemesi.** Host kullanıcısı (502:20) build-args ile `app`
   kullanıcısına eşitlenir; container'ın yazdığı dosyalar host'ta root'a değil
   kullanıcıya (502:20) ait olur. macOS'ta GID 20 (`staff`) imajda çakıştığı için
   `groupadd`/`useradd` idempotent yazıldı (GID/UID zaten varsa yeniden kullanır).

4. **`config/app.php` timezone env'e bağlandı.** Laravel 13 iskeletinde
   `'timezone' => 'UTC'` sabitti, `env()` kullanmıyordu; `.env.example`'daki
   `APP_TIMEZONE=Europe/Istanbul`'u okuması için tek satır env odaklı yapıldı.

5. **`league/flysystem-aws-s3-v3` eklendi.** Laravel iskeletinde suggest paket
   olarak duruyordu; `FILESYSTEM_DISK=s3` ve MinIO doğrulaması için gerekti.

6. **Uygulama portu 8088 (8080 değil).** 8080 bu makinede başka projenin canlı
   WordPress container'ında kullanımda; kullanıcı kararıyla 8088 bağlandı.

## Kabul kriterleri — doğrulama

| # | Kriter | Sonuç |
|---|--------|-------|
| 1 | `make up` → tüm container'lar healthy/running | ✅ tüm servisler up |
| 2 | `http://localhost:8088` → Laravel karşılama | ✅ HTTP 200, başlık "Teşvik CRM" (port **8088**, bkz. karar 6) |
| 3 | `artisan about` → Laravel 13.x, PHP 8.4+, pgsql CONNECTED | ✅ 13.24.0, 8.4.24; `db:show` Connection=pgsql, db=tesvik_crm |
| 4 | `artisan migrate` → varsayılan migration'lar pgsql'de | ✅ users/cache/jobs tabloları oluşturuldu |
| 5 | Redis çalışıyor (Cache::put/get) | ✅ `cache_driver=redis`, put→get turu başarılı |
| 6 | MinIO konsolu açılıyor, bucket oluşmuş | ✅ console HTTP 200; bucket `tesvik-crm` mevcut; Laravel S3 put→get + nesne MinIO'da göründü |
| 7 | Mailpit açılıyor, test maili görünüyor | ✅ web HTTP 200; Laravel test maili Mailpit API'de göründü |
| 8 | Container dosyaları host'ta kullanıcıya ait (root değil) | ✅ container uid:gid = 502:20 |
| 9 | `down && up` → veri kaybolmuyor | ✅ probe satırı `survive-down-up` korundu; 10 tablo duruyor |
| 10 | PLAN.md ve AGENTS.md olduğu gibi duruyor | ✅ main'e göre diff yok |
| 11 | main push edildi; wp-01 push + PR açıldı | ⏳ bu PR |
| 12 | `git ls-files \| grep '\.env$'` boş | ✅ BOŞ |

## Yapılmadı (kapsam dışı)

- Migration (WP-04..07), Filament (WP-14), Pint/Larastan/Pest/CI (WP-02) —
  Laravel iskeletinin getirdiği `phpunit.xml`/`composer.json` dışında kalite
  araçları yapılandırılmadı.
- Model, controller, iş mantığı — hiçbiri yazılmadı.
- Üretim dağıtım dosyaları (tünel, yedek, restore) — WP-20.
