# ADR-0017: Filament paneli ve tasarım sistemi

- **Durum:** Değiştirildi (MFA zorunluluğu ADR-0020 ile değiştirildi)
- **Tarih:** 13.08.2026
- **İlgili:** PLAN.md K-03, K-07, K-11 ve §7.3

## Bağlam

Filament Resource sorguları varsayılan olarak modelin bütün satırlarını döndürür. Uygulamanın yetkilendirme modeli ise her iş verisi listesinin `ScopedQuery` üzerinden `own | team | all | none` kapsamına indirilmesini gerektirir. Ayrıca sonraki beş arayüz paketinin tek vurgu renkli, gölgesiz, yoğun ve açık/koyu temaları ayrı tasarlanmış ortak bir sisteme ihtiyacı vardır.

## Karar

Tek Filament panelinin kimliği `operations`, yolu `/operasyon` olur. Kayıt ekranı açılmaz; e-posta ve parola girişi kullanılır. `User::canAccessPanel()` pasif hesapları reddeder. Filament'in yerleşik TOTP sağlayıcısı kurtarma kodlarıyla açılır. TOTP'nin panel genelinde zorunlu tutulması kararı ADR-0020 ile değiştirilmiştir.

Her somut Resource, `ScopedResource` sınıfından türer. Bu temel sınıf liste sorgusunu sonlandırılmış `getEloquentQuery()` içinde `ScopedQuery` üzerinden geçirir. Detay route binding sırasında kapsam üyeliğini denetler ve kapsam dışı mevcut kayıt için 403 döndürür. Kimliği doğrulanmamış sorguda açık kalmak yerine hata verir.

Mimari Pest testi Resource dizinini tarar ve temel sınıfı atlayan sınıfı reddeder. Laravel policy'leri Filament'in standart `viewAny`, `view`, `create` ve `update` kontrollerinin kaynağıdır; `strictAuthorization` eksik policy metodunu sessizce kabul etmez.

Renklerin tek kaynağı `tokens.css` olur. Açık ve koyu tema aynı paletin matematiksel tersi değildir; her biri ayrı yüzey, kenarlık, metin, vurgu ve durum değerlerine sahiptir. Tema CSS'i yalnız token kullanır. Semantik durum adları veritabanındaki `neutral | info | waiting | success | danger` değerleriyle birebir eşleşir.

## Güvenlik ayrıntısı

Filament TOTP sırrı `encrypted`, kurtarma kodları `encrypted:array` cast'iyle saklanır. `users_audit` trigger dışlama listesine `app_authentication_secret` ve `app_authentication_recovery_codes` eklenir. Test, hem anahtar adlarının hem örnek sır değerlerinin `audit_log` JSON'una girmediğini doğrular.

## Sonuçlar

- Sonraki UI paketlerinin kapsam filtresini unutması statik testte kırmızı sonuç üretir.
- Liste ile doğrudan URL erişimi aynı kapsam kaynağını kullanır.
- TOTP zorunluluğunun güncel sonucu ADR-0020'de kayıtlıdır.
- WP-14 yalnız navigasyon grupları ve örnek Firma Resource'unu içerir; özel operasyon ekranları WP-15–19'a bırakılır.
