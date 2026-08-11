# ADR-0013: Checklist üretimi ve evrak önerisi

- **Durum:** Kabul edildi
- **Tarih:** 11.08.2026
- **İlgili kararlar:** K-03; PLAN.md §6.4, §6.5

## Bağlam

Program sürümünün evrak şablonları dosyaya sözleşmesel anlık görüntü olarak
kopyalanırken kaynak bağı korunmalıdır. Koşullu bir evrak sonradan kapsamdan
çıktığında satırı otomatik silmek, yüklenmiş dosyaları ve karar kanıtını
kaybettirir. Aynı koşul değişmeden kaldığı sürece her gün yeni öneri üretmek de
PM'in çalışma kuyruğunu kirletir.

## Karar

### Üretim ve idempotans

`ChecklistGenerator`, dosya satırını `FOR UPDATE` ile kilitler ve yalnız bağlı
program sürümünün etkin şablonlarını okur. Koşulsuz şablonlar doğrudan,
koşullular ise ADR-0012'deki ortak `ConditionEvaluator` başarılı olduğunda
oluşturulur. Her satır ad, açıklama, zorunluluk ve koşul anlık görüntülerinin
yanında şablon ile program sürümü kimliklerini de taşır.

İdempotans, dosya kilidi altında `deal_id + source_doc_template_id` eşleşmesiyle
`firstOrCreate` kullanılarak sağlanır. Kilit aynı dosyanın eşzamanlı iki
üretimini sıraya sokar; ikinci çağrı mevcut kaynak satırlarını görür. Ad-hoc
evraklarda şablon kaynağı `NULL` olduğu için bu eşleşme uygulanmaz ve her PM
talebi ayrı bir satırdır.

Dosya açma uygulama servisi, dosyayı oluşturduğu transaction içinde üreticiyi
açıkça çağırır. Eloquent `created` gözlemcisi kullanılmaz; böylece import,
migration ve düşük seviye bakım işlemleri gizli iş yan etkisi üretmez. Üretim,
aktivite ve domain olayı/outbox kaydı aynı transaction'dadır.

### Yeniden değerlendirme zamanı

Mevcut koşul bağlamının firma tarafındaki `city`, dosya tarafındaki
`requested_amount` alanları değiştiğinde ilgili model gözlemcileri yalnızca
`ChecklistReevaluator` servisini çağırır. Gözlemciler karar vermez; bütün iş
kuralı Document servisindedir. Yeni koşul alanları DSL'e eklendiğinde bu tetik
listesi aynı değişiklikte genişletilmelidir.

Koşul sağlanır hale gelirse eksik kaynak satırı eklenir ve atanmış PM'e tek bir
uygulama içi bildirim gönderilir. Koşul artık sağlanmıyorsa belge güncellenir
ama silinmez.

### Önerinin temsili

Öneriler ayrı `document_requirement_suggestions` tablosunda tutulur. Bu seçim,
tek bir belge için zaman içinde birden fazla koşul değişimi ve insan kararının
ayrı satırlar halinde okunabilmesini sağlar; belge üzerindeki tek bir öneri
kolonu bu geçmişi ezebilirdi.

- `reason` ile yerelleştirilebilir olay kodu, `reason_parameters` ile belge adı
  anlık görüntüsü saklanır.
- `pending`, `accepted`, `rejected` insan kararını; `superseded`, koşul tekrar
  sağlandığı için bekleyen önerinin otomasyonla geçersizleşmesini gösterir.
- `deal_documents.condition_matches` son değerlendirme sonucudur. Sonuç
  değişmedikçe ikinci bir bekleyen öneri üretilmez; doğruya dönüp yeniden yanlış
  olursa yeni bir öneri oluşturulabilir.
- Öneri satırı DB trigger'ıyla silinemez ve belge başına aynı anda en fazla bir
  bekleyen öneri bulunur.

Kabul kararında belge `not_required` olur; ret kararında statüsü değişmez.
Belge ve ona bağlı `files` satırları iki kararda da korunur. Öneri otomasyon,
karar kullanıcı aktör bağlamıyla hem okunabilir aktiviteye hem trigger tabanlı
denetim kaydına düşer.

## Sonuçlar

Checklist tekrar çalıştırılabilir ve kaynak kuralına kadar izlenebilir. Koşul
kalkması veri kaybına yol açmaz; kurumun belgeyi yine isteyebileceği durumda
son söz PM'dedir. Dosya yükleme, sürümleme ve geçerlilik hesabı WP-11'e; talep
e-postası ve PDF üretimi sonraki pakete bırakılmıştır.
