# ADR-0011: Referans ve demo seeder'larını ayır

- **Durum:** Kabul edildi
- **Tarih:** 11.08.2026
- **İlgili kararlar:** K-05, K-09, K-11

## Bağlam

Statüler, geçişler, roller, izinler ve evrak şablonları üretimde de kurulması
gereken yapılandırma verisidir. Kurgusal kullanıcı ve iş kayıtlarının ise
üretime sızması veri kalitesini ve operasyon güvenini bozar. Ayrıca statü tonu
serbest metin olduğunda yönetim ekranı tasarım sistemini atlayarak doğrudan hex
değeri kaydedebilir.

## Karar

- `ReferenceDataSeeder` üretimde güvenle çalışır ve doğal anahtarlarla
  idempotent upsert yapar. `DatabaseSeeder` yalnız bunu çağırır.
- `DemoDataSeeder`, ortam `production` ise herhangi bir yazma işleminden önce
  hata verir; diğer ortamlarda önce referans verisini güvenceye alır.
- İlk workflow revizyonunun zorunlu `changed_by` bağı, rastgele parolalı ve
  pasif bir teknik tohumlama kimliğiyle karşılanır. Demo hesap sayılmaz ve
  oturum açamaz.
- `statuses.color` kolon adı mevcut model sözleşmesini korumak için değişmez;
  değerleri `neutral`, `info`, `waiting`, `success`, `danger` semantik token
  kümesiyle PostgreSQL CHECK üzerinden sınırlanır. WP-14 gerçek renkleri tek
  token dosyasında eşler.
- İzinler `<kaynak>.<eylem>` biçiminde İngilizce ve tahmin edilebilir kodlarla
  adlandırılır. Satır görünürlüğü `deal.view_own|team|all`, teknik yönetim
  `system.*`, belge indirme ise ayrı `document.download` iznidir.
- Zorunlu evrak geçiş koşulunun veri sözleşmesi
  `deal.required_documents.status all_in [accepted, not_required]` olarak
  kaydedilir; değerlendirme WP-09/10 kapsamındadır.

## Sonuçlar

Tekrarlanan kurulum referans kayıtlarını çoğaltmaz. Üretim kurulumu demo
verisinden varsayılan olarak korunur. Sistem Yöneticisi rolü teknik izinleri
alırken `deal.view_all` ve `document.download` izinlerini otomatik almaz.
