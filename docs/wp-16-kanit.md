# Bizlife CRM — WP-16 doğrulama kanıtı

## Görsel doğrulama

- Açık tema dosya panosu: `docs/screenshots/wp-16-board-light.jpg`
- Açık tema dosya detayı: `docs/screenshots/wp-16-detail-light.jpg`
- Koyu tema evrak checklist: `docs/screenshots/wp-16-checklist-dark.jpg`
- Reddedilen geçiş mesajı: `docs/screenshots/wp-16-transition-rejected-dark.jpg`

Görseller yalnız `DemoDataSeeder` tarafından üretilen kurgusal firma, kişi ve dosya verilerini içerir.

## Otomatik kanıt

`tests/Feature/Filament/DealOperationsScreensTest.php` şunları doğrular:

- pano kapsamı ve kapsam dışı detay URL'sinde 403;
- `5/8 geldi · 2 eksik · 1 incelemede · 1 süresi doldu` türev sayaçları;
- `StatusMachine` ret mesajının ekranda aynen görünmesi;
- ret/yeni sürüm isteği gerekçe doğrulaması;
- sürüm artışı ve eski sürümlerin görünürlüğü;
- bekleyen önerinin pano/checklist görünürlüğü ve iki karar sonucu;
- izinli firma kontağına doğru eksik evrak gövdesi ve kuyruk işi;
- izinsiz rolde aksiyonun gizlenmesi ve doğrudan çağrının 403 olması;
- bildirim alıcısında sistem kullanıcısı/harici adres ayrıklığı.

## Kırmızı/yeşil koruma kanıtı

Checklist'teki `document.approve` görünürlük kontrolü geçici olarak kaldırıldığında izinsiz rol testi `İncelemeye al` metnini gördüğü için **1 test başarısız** oldu. Kontrol geri getirildiğinde aynı filtre **1 test, 2 assertion** ile geçti. Geçici ihlal commit edilmedi.
