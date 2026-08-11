# ADR-0001 · `config/app.php` dosyasını publish et

## Durum

Kabul edildi

## Bağlam

Laravel 13 iskeletinin paket içindeki uygulama ayarı saat dilimini sabit `UTC`
olarak tanımlıyordu. Projenin `Europe/Istanbul` saat dilimini Laravel'in
yapılandırma sistemi üzerinden ve ortam değişkeniyle yönetmesi gerekiyor.

## Karar

`config/app.php` uygulama deposuna publish edildi. `timezone` değeri
`APP_TIMEZONE` ortam değişkeninden okunur; örnek ortamın değeri
`Europe/Istanbul` olur.

## Sonuçlar

Laravel ve PHP tarih işlemleri tek uygulama ayarını kullanır. Buna karşılık
framework yükseltmelerinde paket içindeki yeni `app.php` anahtarları otomatik
gelmez; farklar incelenip yerel dosyaya elle birleştirilmelidir.

## Alternatifler

`bootstrap/app.php` içinde `date_default_timezone_set()` çağırmak değerlendirildi.
Bu seçenek PHP saat dilimiyle Laravel ayarını iki ayrı yerde tutacağı ve bakımda
ayrışma riski yaratacağı için seçilmedi.
