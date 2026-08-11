# ADR-0020: Müşteri tercihleri ve ürün kimliği

## Durum

Kabul edildi

## Bağlam

WP-16 sonrasında müşteri, bütün panel kullanıcılarının ilk girişte TOTP kurmaya
zorlanmamasını istedi. Şirket Yetkilisi hesabı bütün iş verisini gördüğü için bu
tercih hesap ele geçirilmesi riskini artırır.

## Karar

TOTP varsayılan olarak kapalı ve isteğe bağlıdır. Kullanıcı kendi profilinden
açabilir ve kapatabilir. Açık hesapta giriş kodu ve kurtarma kodları kullanılmaya
devam eder; kapalı hesap parola sonrasında kurulum ekranına yönlendirilmez.

MFA sırları `audit_log` JSON'undan çıkarılmaya devam eder. Açma ve kapatma olayı,
hassas olmayan `users.app_authentication_enabled_at` alanındaki değişiklik olarak
trigger tabanlı `audit_log` tablosuna yazılır. Bu tercih bir dosya, fırsat veya
belge aktivitesi değil hesap güvenliği değişikliği olduğu için kullanıcı zaman
tüneli olan `activities` yerine değiştirilemez teknik denetim katmanında tutulur.

## Sonuçlar

- Müşterinin istediği daha düşük giriş sürtünmesi sağlanır.
- Şirket Yetkilisi hesabının geniş veri erişimi nedeniyle artan risk müşteri
  tarafından bilinerek kabul edilmiştir.
- Sırlar denetim verisine girmezken olayın aktörü ve zamanı kanıtlanabilir kalır.

## Alternatifler

Şirket Yetkilisi ve Sistem Yöneticisi için zorunlu MFA ile bütün roller için
zorunlu MFA reddedildi; ikisi de müşterinin açık kullanım tercihiyle çelişiyor.
