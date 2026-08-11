# ADR-0014 · Belge yükleme ve erişim hattı

**Durum:** Kabul edildi  
**Tarih:** 11.08.2026  
**Paket:** WP-11

## Kararlar

### Aynı içerik yeniden sürüm değildir

Aynı `deal_document` altında aynı SHA-256 değeri ikinci kez yüklenmez. Servis
Türkçe bir mükerrer uyarısı verir; kontrol belge satırı kilitlendikten sonra
transaction içinde tekrarlandığı için eşzamanlı istekler de sıraya girer. Sürüm,
içeriği değişmiş bir teslimi ifade eder. Aynı baytları yeni sürüm saymak geçmişi
şişirir ve inceleme izini yanıltır.

### Taraması tamamlanmayan dosya kapalıdır

`pending`, `failed` ve `infected` dosyalar indirilemez; yalnız `clean` sonucu
erişime açılır. Kullanılabilirlikten önce güvenliği seçiyoruz. Tarayıcı
`VirusScanner` sözleşmesinin arkasındadır. WP-20'de ClamAV sürücüsü bu sözleşmeye
bağlanacaktır. Geliştirme stub'ı yalnız `local` ve `testing` ortamlarında temiz
sonucu verebilir; üretimde çalışmayı açıkça reddeder.

### Erişim talebi indirme değildir

Uygulama imzalı, kısa ömürlü kendi proxy URL'sini üretir. Üretimde
`document.access_requested`, response gövdesi nesne deposundan başarıyla
aktarıldıktan sonra `document.downloaded` yazılır. Böylece açılmamış bir bağlantı
indirme olarak raporlanmaz. Yetki hem bağlantı üretiminde hem proxy çağrısında
yeniden denetlenir.

### Tamamlık damgası mevcut durumu temsil eder

`all_required_accepted_at`, bütün zorunlu evraklar `accepted/not_required`
olduğu anda ilk kez set edilir. Yeni sürüm yükleme veya süre dolumu tamamlığı
bozarsa `NULL`a döner; yeniden tamamlandığında yeni zaman yazılır. Bu alan bir
kez olmuş tarih değil, PLAN.md §13 raporlarının kullandığı tamamlık döneminin
başlangıcıdır. `first_document_received_at` ise ilk teslimin kalıcı tarihidir ve
sonradan değişmez.

### Nesne deposu ve veritabanı telafiyle atomiktir

S3 ve PostgreSQL ortak transaction sunmadığı için nesne önce opaque UUID ile
yazılır, metadata/aktivite/outbox tek DB transaction'ında oluşturulur. DB
transaction'ı başarısızsa sahipsiz nesne silinir. DB commit olmuşsa kuyruk
dağıtımındaki sonraki bir hata nesneyi silmez; `pending` kayıt tekrar taranabilir.
