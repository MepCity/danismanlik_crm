# ADR-0003: Modüler monolit sınırları

- Durum: Kabul edildi
- Tarih: 2026-08-10

## Bağlam

Uygulama tek dağıtım birimi ve tek veritabanı olarak kalırken CRM, dosya,
program, belge ve erişim alanlarının birbirinin iç ayrıntılarına bağımlı hale
gelmemesi gerekir. Aksi durumda sonraki mobil uygulama veya müşteri portalı aynı
iş kurallarını güvenle yeniden kullanamaz.

## Karar

`app/Domain` altında `Crm`, `Deal`, `Program`, `Document` ve `Access` modülleri
bulunur. Her modül `Models`, `Services`, `Events`, `Actions`, `DTOs` ve
`Exceptions` katmanlarına sahiptir. HTTP ve Filament katmanları iş mantığı
taşımaz; modül servislerini arayüzleri üzerinden çağırır.

Bir modül başka bir modülün `Models` sınıflarına doğrudan erişemez. Modüller
arası iletişim servis arayüzü, DTO veya domain olayıyla yapılır. `Support` yalnız
gerçekten ortak ve iş alanından bağımsız yapı taşlarını içerir; bütün modüller
burayı kullanabilir.

Bu sınır ek bağımlılık olmadan özel PHPStan kuralıyla denetlenir. Kural hem
`use` ifadelerini hem tam sınıf adlarını çözümler ve modüller arası `Models`
erişimini `architecture.crossModuleModelAccess` hatasıyla reddeder.

## Sonuçlar

Tek uygulamanın transaction kolaylığı korunur. Modül iç ayrıntıları doğrudan
paylaşılamadığı için servis/DTO sözleşmeleri açık tutulur. Yeni bir istisna
gerekiyorsa kuralı susturmak yerine bu ADR değiştirilir.
