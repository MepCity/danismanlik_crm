# Bizlife CRM

Teşvik ve hibe danışmanlığı yapan bir şirket için dar kapsamlı operasyon sistemi:
pazarlamanın aramasından, dosyanın statü ve evrak takibiyle sonuçlanmasına kadar
olan akışı yönetir. Genel bir CRM değildir; tek değeri programa göre değişen,
sürümlenen evrak şablonu motorudur.

## Gereksinim

Yalnızca **Docker** (Docker Desktop veya Docker Engine + Compose v2). Host'a PHP,
Composer, PostgreSQL veya Redis **kurulmaz** — yerel PHP (`php@7.1`) kırık olduğu
için tüm PHP/artisan/composer komutları container içinde çalışır.

## Hızlı başlangıç

```bash
make up
```

İlk ayağa kalkışta imaj derlenir, `app` container'ı `.env`'i `.env.example`'dan
üretir, uygulama anahtarı oluşturulur. Açılış sonrası:

| Servis            | Adres                          | Açıklama                          |
|-------------------|--------------------------------|-----------------------------------|
| Uygulama (web)    | http://localhost:8088          | Laravel karşılama sayfası         |
| Mailpit           | http://localhost:8025          | Giden e-postaları yakalar         |
| MinIO konsolu     | http://localhost:9001          | S3 uyumlu nesne deposu (minioadmin / minioadmin) |
| PostgreSQL        | localhost:55432                | `tesvik` / `tesvik` / `tesvik_crm` |

> Portlar `.env`'deki `WEB_PORT`, `PUBLISH_DB_PORT`, `PUBLISH_*` değişkenlerinden
> ayarlanır. 8088 seçilmesinin nedeni: 8080 bu makinede başka projede kullanımda.

## Sık kullanılan komutlar

```bash
make up            # servisleri ayağa kaldır (imajı derler)
make down          # durdur (volumeler kalır)
make logs          # canlı log akışı
make ps            # servis durumu
make shell         # app container'ında bash
make artisan a="about"      # PHP + Laravel + DB sürümünü göster
make artisan a="migrate"    # migration'ları çalıştır
make tinker        # Laravel tinker
make seed          # yalnız üretimde güvenli referans/yapılandırma verisi
make seed-demo     # referans + kurgusal demo verisi (production'da reddedilir)
make test          # testleri çalıştır
make test-coverage # HTML kapsama raporu üret
make lint          # kod biçimini denetle
make lint-fix      # kod biçimini düzelt
make analyse       # Larastan level 6 analizi
make composer a="require spatie/laravel-permission"  # paket ekle
make fresh         # migrate:fresh + yalnız referans/yapılandırma verisi
make help          # tüm komutlar
```

## Seeder komutları ve demo hesapları

`make seed`, statü/geçiş, rol/izin, ilk workflow revizyonu ve Yeşil Sanayi
program şablonunu idempotent olarak yükler. Üretimde çalıştırılabilir. İlk
workflow revizyonunun `changed_by` bağı için bu işlem ayrıca rastgele parolalı,
pasif `system-seeder@localhost.invalid` teknik kimliğini oluşturur.

`make seed-demo`, önce aynı referans verisini, ardından tamamen kurgusal firma,
kişi, fırsat, dosya ve evrak satırlarını yükler. Komut `production` ortamında
herhangi bir demo verisi yazmadan hata verir. Tüm demo hesaplarının parolası
`Demo123!` değeridir:

| Rol | E-posta |
|---|---|
| Pazarlama | `pazarlama@demo.invalid` |
| Proje Yöneticisi | `proje.yoneticisi@demo.invalid` |
| Şirket Yetkilisi | `sirket.yetkilisi@demo.invalid` |
| Sistem Yöneticisi | `sistem.yoneticisi@demo.invalid` |

`make fresh`, mevcut şemayı ve içindeki veriyi siler; migration'ları yeniden
çalıştırır ve yalnız referans verisini yükler. Demo veri yüklemez.

## Mimari kararlar ve kurallar

Bu depo yalnızca iki kalıcı belge taşır; kararların tamamı orada:

- **[`PLAN.md`](PLAN.md)** — ADR'ler (K-01…K-11), veri modeli, statü mimarisi,
  kapsam sınırı, yol haritası.
- **[`AGENTS.md`](AGENTS.md)** — bağlayıcı mimari ve teslim kuralları, git akışı,
  tasarım sistemi, paket haritası (WP-01…WP-21).

İş mantığı controller/Filament Resource'ta değil servis katmanındadır. Statüler,
izinler ve evrak listeleri koda gömülmez; veritabanı satırıdır. Kayıtlar silinmez.
Denetim iki katmanlıdır. Tüm arayüz metni Türkçe, tüm kod İngilizcedir.

## Teknoloji

PHP 8.4 · Laravel 13.24 · PostgreSQL 17 · Redis · S3 uyumlu (MinIO) · Docker
Compose · Pest · Larastan · Laravel Pint.

Katkı akışı ve PR öncesi kontroller için [`CONTRIBUTING.md`](CONTRIBUTING.md)
belgesine bakın.
