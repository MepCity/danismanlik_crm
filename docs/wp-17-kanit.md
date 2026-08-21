# Bizlife CRM — WP-17 doğrulama kanıtı

> **Geçerlilik notu — 21.08.2026:** WP-17'deki arama izni korumasına ait eski
> kanıtlar müşteri kararıyla geri çekilmiştir. Güncel davranışta arama izni
> alanı veya engeli yoktur; aşağıdaki otomatik kanıt listesi yeni davranışı
> anlatır. E-posta izni kontrolü bu karardan etkilenmemiştir.

## Görsel doğrulama

- Masaüstü arama listesi: `docs/screenshots/wp-17/arama-listesi-masaustu.png`
- Dar ekran arama listesi (390 × 844): `docs/screenshots/wp-17/arama-listesi-dar-ekran.png`
- Fırsat panosu: `docs/screenshots/wp-17/firsat-panosu.png`
- İş alındı dönüşüm formu: `docs/screenshots/wp-17/donusum-akisi.png`

Görseller yalnız `DemoDataSeeder` tarafından üretilen kurgusal firma, kişi,
geçersiz örnek telefon ve `.invalid` hesap verilerini içerir. Tarayıcı konsolu
görsel tur sonunda hata ve uyarı üretmedi.

## Otomatik kanıt

`tests/Feature/Crm/MarketingActionsTest.php` ve
`tests/Feature/Filament/MarketingScreensTest.php` şunları doğrular:

- pazarlamacının yalnız kendi fırsatlarını görmesi ve kapsam dışı doğrudan
  URL'nin 403 dönmesi;
- arama izni alanı olmadan telefon ve görüşme sonucu aksiyonlarının çalışması;
- veri kaynağının action ve PostgreSQL katmanlarında zorunlu olması;
- callback tarih/sorumlu ve lost gerekçesinin veritabanı yapılandırmasından
  okunarak doğrulanması;
- beş aramanın beş ayrı `interactions` satırı olması ve statüyü değiştirmemesi;
- bugün/geçmiş aramalarının seçilmesi ve gecikenlerin önce gelmesi;
- hızlı sonucun iki adımda ayrı görüşme üretmesi;
- dönüşümde dosya, program sürümü, checklist, başlangıç statüsü, geçmiş,
  bildirim, aktivite ve outbox olayının ayrı ayrı oluşması;
- checklist ortasında bilinçli hata üretildiğinde dosya dahil bütün yazımların
  geri alınması;
- aynı fırsatın ikinci kez dönüştürülememesi.

`tests/Feature/Crm/MarketingMigrationTest.php`, migration için `up → down → up`
çevrimini gerçek PostgreSQL üzerinde doğrular.

## Kırmızı/yeşil koruma kanıtı

- Veri kaynağı boş kişi doğrudan yazıldığında PostgreSQL
  `contacts_data_source_not_blank` ihlali üretir; test bunu bilinçli oluşturup
  yakalar.
- Callback/lost hedeflerine zorunlu alanlar olmadan doğrudan statü yazımı
  trigger hatası üretir; testler iki ihlali de oluşturur.
- Atomiklik testindeki sahte checklist üreticisi önce bir belge satırı yazıp
  sonra bilinçli exception fırlatır; dosya ve belge satırlarının ikisinin de
  kalmadığı doğrulanır.
- Kapsam testleri doğrudan URL/metot çağrılarıyla sunucu tarafı korumalarını
  sınar; yalnız buton görünürlüğüne güvenmez.
