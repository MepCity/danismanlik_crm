# ADR-0004: Domain olayı ve outbox ayrımı

- Durum: Kabul edildi
- Tarih: 2026-08-10

## Bağlam

Bildirim, aktivite ve otomatik görev gibi iç tüketiciler iş işlemiyle tutarlı
olmalıdır. Dış webhook yönetimi ise henüz gerçek bir tüketici olmadan imzalama,
secret rotasyonu ve teslimat günlüğü yükü getirir.

## Karar

İç olaylar `DomainEvent` taban sınıfından türetilir ve geçmiş zamanla
adlandırılır. Laravel olay sistemi olayı senkron dağıtır. Ortak dinleyici,
`OutboxWriter` arayüzünü olayın fırlatıldığı transaction içinde çağırır; böylece
iş kaydı ile outbox kaydı ileride atomik olacaktır.

WP-03 yalnız sözleşmeyi ve kuyruk işi iskeletini sağlar. Outbox tablosu ve kalıcı
yazıcı WP-07'de ekleneceğinden varsayılan geçici yazıcı sessiz veri kaybetmek
yerine `WP-07` işaretli `LogicException` fırlatır. Kuyruk işleri
`AutomationJob` tabanından türeyerek transaction aktör kaynağını otomatik olarak
`automation` yapar.

Dışa açık webhook bu kararın parçası değildir ve Faz 3'e bırakılmıştır.

Aktör bilgisi HTTP isteğini veya kuyruk işini baştan sona transaction içine
almaz. Middleware ve `AutomationJob`, aktörü yalnızca scoped bir tutucuda
saklar. Laravel'in `TransactionBeginning` olayı her gerçek transaction
başladığında tutucudaki değerleri o connection'a `SET LOCAL` ile yazar. Uzun
HTTP istekleri, dosya aktarımları ve dış servis çağrıları böylece gereksiz yere
PostgreSQL transaction'ı açık tutmaz.

Bu seçimin kabul edilen bedeli şudur: explicit transaction dışında yapılan tek
satırlık yazımlar aktör bağlamı taşımaz ve denetim trigger'ı tarafından
`system/unknown` olarak kaydedilir. Mimari, domain yazımlarını servis katmanında
toplar ve bu servisler atomik işlemler için explicit transaction açar. Bağlamın
önemli olduğu iş yazımları bu nedenle aktörü alırken, transaction açmayı
unutmanın sonucu sahte bir kullanıcı atfı değil açıkça bilinmeyen kaynak olur.
Bu davranış PLAN.md §8.2 ile uyumludur.

## Sonuçlar

Domain servisleri belirli dinleyicilere bağlanmaz ve testlerde `OutboxWriter`
fake'i kullanılabilir. WP-07 tamamlanana kadar gerçek domain olayı üretmek
bilinçli olarak erken hata verir.
