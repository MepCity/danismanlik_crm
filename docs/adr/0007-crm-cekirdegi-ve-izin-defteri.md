# ADR-0007: CRM çekirdeği ve izin defteri şeması

- Durum: Kabul edildi
- Tarih: 2026-08-11

## Bağlam

WP-05 firma, kişi, pazarlama fırsatı ve görüşme kayıtlarını kurarken koşullu
evrak motorunun güvenilir il verisine ve KVKK/İYS denetiminde geçmiş durumu
yeniden üretebilen bir izin kaynağına ihtiyaç duyar. Program, dosya ve iş akışı
tabloları bağımlılık sırası gereği henüz mevcut değildir.

## Karar

### İl kodu

`companies.city`, `01`–`81` aralığındaki iki haneli plaka kodunu taşır ve
PostgreSQL `CHECK` constraint ile korunur. Serbest il adı tutulmaz. Plaka kodu
kararlı, kısa ve yazım varyasyonlarından bağımsızdır; koşullu evrak ifadeleri
örneğin deprem illerini `['02', '21', '27', ...]` olarak karşılaştırır. Türkçe il
adı sunum katmanında kontrollü kod listesinden gösterilecektir.

Ayrı bir `cities` referans tablosu değerlendirilmiş, WP-05'e zorunlu olmayan
altıncı bir iş tablosu ve migration içinde referans veri yükleme ihtiyacı
doğuracağı için seçilmemiştir. Kod kümesi Türkiye'nin 81 ili için kapalı ve
değişmez olduğundan `CHECK` daha dar çözümdür.

### Vergi numarası

`tax_number` nullable ve tekildir. Verildiğinde yalnızca 10 haneli VKN veya 11
haneli TCKN kabul edilir. Yalnız VKN kabul etmek şahıs işletmelerini dışarıda
bırakacağından iki biçim de desteklenir; değerlerin algoritmik doğrulaması bu
migration paketinin kapsamı değildir.

### İzin geçmişi

`communication_consents` her kanal ve amaç için onay, ret veya geri alma
olayını ayrı satırda saklar. Güncel kayıt `(contact_id, channel, purpose,
effective_from DESC)` sırasındaki ilk satırdır. PostgreSQL tetikleyicisi `UPDATE`
ve `DELETE` işlemlerini uygulama dışından da engeller. `contacts` üzerindeki dört
izin alanı nullable/güncel sorgu özetidir; tek gerçek kaynak değildir ve
senkronizasyonu WP-12/13'te uygulanacaktır.

### Geçici bağlar

Bağımlılık sırası nedeniyle aşağıdaki açıklar bilinçlidir:

- `leads.interested_program`, WP-06'da `program_versions` oluşturulana kadar
  serbest metin veya kod taşır; WP-06'da gerçek yabancı anahtar bağına
  dönüştürülecektir.
- `leads.status`, WP-07'de `statuses` oluşturulana kadar kısa kod taşır; WP-07'de
  statü yabancı anahtarı ve DB tabanlı geçiş yapısıyla değiştirilecektir.
- `interactions.subject_type`, kontrollü kümede `deal` kodunu şimdiden kabul
  eder; `deals` WP-06'da oluşmadığı için bu dalda yabancı anahtar kurulamaz.
  WP-06'da `deal` öznesinin bütünlüğü bağlanacak, kontrollü polymorphic sözleşme
  korunacaktır.

## Sonuçlar

Koşullu evrak motoru il yazım hatalarıyla sessizce yanlış sonuç üretmez. İzin
geçmişi üzerine yazılamaz ve belirli bir tarihteki hukuki dayanak ile kanıt
yeniden bulunabilir. Geçici program/statü/`deal` bağları şema yorumlarında da
işaretlidir; WP-06 ve WP-07 tamamlanmadan kalıcı kabul edilmez.
