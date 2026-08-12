# WP-20 üretim doğrulama kanıtları

Bu belge test/build/tatbikat çalıştırıldığında gerçek çıktıyla güncellenir.
Hiçbir sır, müşteri verisi, sağlayıcı hesap kimliği veya özel URL yazılmaz.

## Prod build ve çalışma

İlk prod build Filament CSS import'u için builder'da `vendor` bulunmadığını
yakaladı. İkinci ayağa kaldırma ARM64 ClamAV manifesti, dev paket manifesti ve
WAL volume sahipliği sorunlarını gösterdi. Düzeltilen son build çıktısı:

```text
tesvik-crm/app:wp20-drill    Built
tesvik-crm/web:wp20-drill    Built
tesvik-crm/backup:wp20-drill Built
Environment: production
Debug Mode: OFF
Config: CACHED
Routes: CACHED
Views: CACHED
opcache.validate_timestamps=0
web health: healthy; /up: HTTP 200
```

`app`, `web`, `db`, `redis`, `clamav`, `queue` ve `scheduler` çalıştı. Bütün
servislerin `Publishers` kayıtlarında `PublishedPort=0`; compose dosyasında
`ports` ve host network yok.

## Tünel

Hesapsız Cloudflare Quick Tunnel yalnız doğrulama için compose ağına bağlandı ve
tatbikat sonunda kapatıldı:

```text
DNS Resolution: PASS
UDP Connectivity: PASS (QUIC)
TCP Connectivity: PASS (HTTP/2)
Cloudflare API: PASS
Registered tunnel connection: location=ist02 protocol=quic
TUNNEL_HTTP=200
TUNNEL_TLS=0
```

## ClamAV

Uygulamanın `VirusScanner` bağlaması üzerinden EICAR ve kesinti sonuçları:

```text
EICAR => infected
ClamAV durduruldu => failed
ClamAV health => healthy
```

Dosya erişimi yalnız `scan_result=clean` kabul ettiği için `infected`, `failed`
ve `pending` dosyalar indirilemez.

## Yedek, WAL ve geri dönüş

Tam çıktı özeti [geri dönüş tatbikatı](geri-donus-tatbikati.md) belgesindedir.
Şifreli repository `restic check` ile doğrulandı. Boş `RESTIC_REPOSITORY`
ihlali `[operasyon] HATA` ve exit 1 üretti.

## Evrak replikasyonu

`object-replica` iki bağımsız test S3 sunucusu ve ayrı kimlik bilgileriyle
çalıştırıldı:

```text
31 B transferred
SOURCE_SHA256=debbb682b9d1992c4b6fd48e12f39a68d223caecc9ef32e1dfdd1b3838023bcd
REPLICA_SHA256=debbb682b9d1992c4b6fd48e12f39a68d223caecc9ef32e1dfdd1b3838023bcd
TARGET_RETAINED_AFTER_SOURCE_DELETE=yes
monitor --once => exit 0
```

Kaynak silmesi hedefe yayılmadı; ikinci kopya felaket kurtarma için korundu.

## Partition ve izleme

```text
audit:ensure-partitions --months=3 => 4 aylık partition hazır
audit_log_202608
audit_log_202609
audit_log_202610
audit_log_202611
monitor --once => exit 0
```

Scheduler `audit:ensure-partitions` komutunu her gün 00:15 için listeledi.
Monitor disk, kuyruk, son yedek yaşı ve gelecek ay partition'ını birlikte
doğruladı.

## Negatif korumalar

```text
boş APP_KEY üretim ön kontrolü => exit 1
boş RESTIC_REPOSITORY yedek => exit 1
boş OBJECT_REPLICA_SECRET_ACCESS_KEY replikasyon => exit 1
aynı birincil/replikasyon erişim kimliği => exit 1
ClamAV kararı yok/servis kapalı => failed
```

## Kod kalite doğrulaması

```text
Laravel Pint => PASS (345 dosya)
Larastan => PASS (hata yok)
Pest => 258 test geçti (1.080 doğrulama)
```

GitHub Actions sonucu PR açıldıktan sonra uzak CI üzerinden ayrıca izlenir.
