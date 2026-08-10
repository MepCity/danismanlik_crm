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

## Sonuçlar

Domain servisleri belirli dinleyicilere bağlanmaz ve testlerde `OutboxWriter`
fake'i kullanılabilir. WP-07 tamamlanana kadar gerçek domain olayı üretmek
bilinçli olarak erken hata verir.
