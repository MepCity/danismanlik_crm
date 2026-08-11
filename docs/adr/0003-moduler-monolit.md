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

Sınırın koruduğu şey ORM ilişki grafiği değil, **iş mantığı bağımlılığıdır**.
`App\Domain\<Modül>\Models` içindeki Eloquent modelleri başka modüllerin
modellerini ilişki tanımında doğrudan ve hassas jeneriklerle kullanabilir. Model
grafiğinin gerçek sınıfları göstermesi Larastan'ın ilişki sonuçlarını doğrulaması
için gereklidir.

Bir modülün `Services`, `Actions`, `Events`, `DTOs` ve `Exceptions` katmanları
ise başka bir modülün modeline doğrudan erişemez. Modüller arası iş mantığı
iletişimi servis arayüzü, DTO veya domain olayıyla yapılır. `Support` yalnız
gerçekten ortak ve iş alanından bağımsız yapı taşlarını içerir; bütün modüller
burayı kullanabilir.

Bu sınır ek bağımlılık olmadan özel PHPStan kurallarıyla denetlenir. Kurallar
metot/fonksiyon/property tiplerini, statik çağrıları, `new` ifadelerini ve sınıf
sabiti erişimlerini (`::class` dahil) çözümler. Model namespace'i dışındaki
modüller arası model erişimini `architecture.crossModuleModelAccess` hatasıyla
reddeder.

## Sonuçlar

Tek uygulamanın transaction kolaylığı ve Eloquent ilişki tip güvenliği korunur.
İş mantığı katmanlarında modül iç ayrıntıları doğrudan paylaşılmadığı için
servis/DTO sözleşmeleri açık tutulur. İlk kuralın model katmanını da yasaklayan
fazla geniş ifadesi, registry benzeri kaçış yolları üretip kuralı etkisizleştirdiği
için bu gerçek niyetle daraltılmış; sözdizimi kapsaması ise genişletilmiştir.
