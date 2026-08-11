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

Bağımlılık sırası nedeniyle aşağıdaki açıklar bilinçli açılmıştır:

- [x] `leads.interested_program`, WP-06'da `program_versions` oluşturulduktan
  sonra `interested_program_version_id` adlı nullable gerçek yabancı anahtar
  bağına dönüştürülmüştür. Geçiş sayısal olmayan eski değerleri sessizce
  kaybetmek yerine migration'ı durdurur; böyle bir veri varsa önce açıkça
  eşlenmesi gerekir.
- [x] `leads.status`, WP-07A'da kaldırılmış ve `statuses` tablosuna zorunlu
  `status_id` yabancı anahtarıyla değiştirilmiştir. Koda bağlı `lost` ve
  `callback` CHECK'leri veri tabanlı koşul/guard uygulaması için WP-09'a
  bırakılmıştır.
- [x] `interactions` özne bağı, WP-06'da `lead_id` ve `deal_id` gerçek yabancı
  anahtarlarına dönüştürülmüş; `num_nonnulls(...) = 1` kısıtıyla tam olarak bir
  özne zorunlu kılınmıştır. Kontrollü polymorphic geçici deseni kalıcılaştırılmamıştır.

## Sonuçlar

Koşullu evrak motoru il yazım hatalarıyla sessizce yanlış sonuç üretmez. İzin
geçmişi üzerine yazılamaz ve belirli bir tarihteki hukuki dayanak ile kanıt
yeniden bulunabilir. Geçici program ve `deal` bağları WP-06 ile kapanmıştır.
Statü bağı WP-07A ile kapanmış, WP-05'in bilinçli geçici bağlarının tamamı gerçek
yabancı anahtarlara dönüştürülmüştür.
