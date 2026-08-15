# ADR-0022: Zaman tüneli ve yorum arayüzü

## Durum

Kabul edildi — yorum görünürlüğü kararı 15.08.2026 tarihinde güncellendi

## Bağlam

Dosya, fırsat ve evrak seviyesindeki aktivite ile yorumlar tek bir okunabilir
akışta gösterilmelidir. WP-13; görünürlük, mention kapsamı, etiket anlık
görüntüsü, çeviri ve sabit sorgu sayısı sözleşmelerini zaten domain katmanında
kurmuştur. Arayüzün bunları yeniden yorumlaması güvenlik ve geçmiş
okunabilirliği açısından ikinci bir kural kaynağı oluşturur.

## Karar

Zaman tüneli ve yorumlar ayrı, yeniden kullanılabilir Livewire sunum
bileşenleridir. Her bileşen kontrollü `lead`, `deal` veya `deal_document`
özne türü ile kimlik alır. Zaman tüneli yalnız `TimelineQuery::paginate()`
sonucunu gösterir; aktivite payload'ı Blade'e verilmez ve cümleler
`ActivityTranslator` çıktısıdır. Filtreleme ve sayfalama sorgu katmanına
iletilir. Otomasyon satırları “Sistem” aktörü ve ayrı durum zeminiyle görünür.

Yorum bileşeni yazma ve düzenlemede yalnız `CommentService` çağırır. Sisteme
müşteri girişi ve müşteri portalı olmayacağı için görünürlük seçimi sunulmaz;
servis tüm yeni yorum ve yanıtları `internal` olarak kaydeder. Önceden oluşmuş
kayıtların görünürlük değeri denetim bütünlüğü için geriye dönük değiştirilmez.
Silme aksiyonu yoktur.

Mention için kullanıcı ham `@[Ad](user:ID)` biçimini yazmaz. Arayüz yalnız
özneyi `view` policy'siyle görebilen etkin kullanıcıları seçicide listeler ve
seçimi kanonik biçimde metne ekler. Okumada bu biçim yalnız `@Ad` olarak
gösterilir. Sunucudaki `CommentService` kapsam kontrolü ikinci ve bağlayıcı
kapı olarak kalır.

## Sonuçlar

- Aynı bileşenler dosya, fırsat ve evrak bağlamında tekrar kullanılabilir.
- Geçmiş statü etiketinin güncel yapılandırmaya göre değişmesi veya ham JSON'un
  arayüze sızması mümkün değildir.
- Müşteriye açık yorum seçeneği ve müşteri okuma modu ürün kapsamından çıkarılmıştır.
- Gerçek zamanlı güncelleme eklenmemiştir; açık gereksinim oluşursa mevcut
  bileşenler olayla yenilenebilir.
