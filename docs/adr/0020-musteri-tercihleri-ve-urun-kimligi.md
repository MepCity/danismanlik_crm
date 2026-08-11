# ADR-0020: Müşteri tercihleri ve ürün kimliği

## Durum

Kabul edildi

## Bağlam

WP-16 sonrasında müşteri, bütün panel kullanıcılarının ilk girişte TOTP kurmaya
zorlanmamasını istedi. Şirket Yetkilisi hesabı bütün iş verisini gördüğü için bu
tercih hesap ele geçirilmesi riskini artırır.

Aynı talep kapsamında ürünün kullanıcıya görünen adı Bizlife CRM olarak
belirlendi. Çalışan geliştirme ortamında ise Docker Compose proje adı,
PostgreSQL veritabanları, MinIO bucket ve imaj etiketi mevcut volume'ları ve
verileri tanımlıyor.

## Karar

TOTP varsayılan olarak kapalı ve isteğe bağlıdır. Kullanıcı kendi profilinden
açabilir ve kapatabilir. Açık hesapta giriş kodu ve kurtarma kodları kullanılmaya
devam eder; kapalı hesap parola sonrasında kurulum ekranına yönlendirilmez.

MFA sırları `audit_log` JSON'undan çıkarılmaya devam eder. Açma ve kapatma olayı,
hassas olmayan `users.app_authentication_enabled_at` alanındaki değişiklik olarak
trigger tabanlı `audit_log` tablosuna yazılır. Bu tercih bir dosya, fırsat veya
belge aktivitesi değil hesap güvenliği değişikliği olduğu için kullanıcı zaman
tüneli olan `activities` yerine değiştirilemez teknik denetim katmanında tutulur.

Panel, giriş ekranı, tarayıcı başlığı, e-posta gönderen adı ve e-posta şablon
başlığı dahil kullanıcıya görünen ürün adı **Bizlife CRM** olur. `APP_NAME`
varsayılanı da bu adı taşır.

Altyapı tanımlayıcıları bilinçli olarak değiştirilmez:

- Docker Compose proje adı: `tesvik-crm`
- PostgreSQL veritabanları: `tesvik_crm`, `tesvik_crm_test`
- MinIO bucket: `tesvik-crm`
- İmaj etiketi: `tesvik-crm/app`

Bu adları şimdi değiştirmek mevcut volume bağlarını koparıp geliştirme
veritabanı ile yüklenmiş evrakları kaybetme riski doğurur; kullanıcıya bir
kazanım sağlamaz. Üretime geçmeden önce ayrı bir iş olarak değerlendirilir.

## Sonuçlar

- Müşterinin istediği daha düşük giriş sürtünmesi sağlanır.
- Şirket Yetkilisi hesabının geniş veri erişimi nedeniyle artan risk müşteri
  tarafından bilinerek kabul edilmiştir.
- Sırlar denetim verisine girmezken olayın aktörü ve zamanı kanıtlanabilir kalır.
- Kullanıcı yüzeyleri tek ürün adında birleşir; altyapı volume'ları korunur.

## Alternatifler

Şirket Yetkilisi ve Sistem Yöneticisi için zorunlu MFA ile bütün roller için
zorunlu MFA reddedildi; ikisi de müşterinin açık kullanım tercihiyle çelişiyor.
