# KVKK alt işleyen ve yurt dışı aktarım envanteri

Bu tablo teknik envanterdir; hukuki görüş yerine geçmez. “Canlı öncesi değer”
boş olan satır tamamlanmadan üretim onayı verilmez. Hesap kimliği, token, parola,
DSN ve erişim anahtarı bu belgeye veya depoya yazılmaz.

| Hizmet | İşlenen/verilen veri | Sağlayıcı ve fiziksel bölge | Aktarım durumu | Canlı öncesi kontrol |
|---|---|---|---|---|
| Ofis sunucusu | CRM veritabanının tamamı, uygulama logları | Makinenin bulunduğu ofis, Türkiye; müşteri ofisi mi yüklenici ofisi mi yazılı belirlenir | Türkiye içi. Yüklenici ofisindeyse veri işleyen sözleşmesi gerekir | Makine sahibi, açık adres, bakım erişimi ve veri işleyen rolü kayda alınır |
| SMTP | Alıcı e-posta adresi, bildirim başlığı/gövdesi ve teslim metadatası | **Henüz seçilmedi — canlıyı bloke eder** | Bölge Türkiye dışında ise yurt dışı aktarım vardır | Sağlayıcı, veri merkezi ülkesi, alt işleyenleri, saklama süresi ve DPA doğrulanır |
| Hata izleme (Sentry) | İstisna, stack trace, rota ve teknik bağlam; varsayılan PII gönderimi kapalı | Sentry SaaS hesabında seçilen bölge bu satıra yazılır; seçim yapılmadı | Türkiye dışı bölge seçilirse yurt dışı aktarım vardır | DSN sonrası test olayı gönderilir; olayda müşteri adı, evrak adı/içeriği, e-posta ve token bulunmadığı kontrol edilir |
| Tünel (Cloudflare) | IP, hostname, HTTP/TLS bağlantı metadatası; uygulama trafiği transit geçer | Cloudflare küresel ağı, Türkiye dışı işleme olasıdır | Yurt dışı aktarım değerlendirmesi zorunludur | DPA, alt işleyen listesi, log saklama ayarı ve uygun aktarım güvencesi hukukça onaylanır |
| Belge nesne deposu | Yüklenen evrakların şifreli nesneleri ve opaque UUID anahtarları | **Yönetilen S3 sağlayıcısı ve Türkiye bölgesi henüz seçilmedi — canlıyı bloke eder** | Türkiye bölgesi zorunlu; başka bölge ancak uygun güvenceyle | Bölge, server-side encryption, private bucket, versioning, erişim logu ve silme/saklama ayarı kanıtlanır |
| Belge replikasyon hedefi | Birincil kovadaki evrakların ikinci kopyası ve nesne anahtarları | **Birincil hesaptan ayrı S3 hesabı/sağlayıcısı henüz seçilmedi — canlıyı bloke eder** | Sağlayıcı ve bölgeye göre ayrıca değerlendirilir | Ayrı kimlik bilgisi, private bucket, versioning/immutability, şifreleme ve örnek SHA-256 karşılaştırması kanıtlanır |
| Şifreli yedek hedefi | `restic` ile istemci tarafında şifreli PostgreSQL taban yedeği ve WAL | **Belge hesabından ayrı off-site S3 hesabı/sağlayıcısı henüz seçilmedi — canlıyı bloke eder** | Şifreli olsa da sağlayıcı ve bölge envantere girer; yurt dışıysa aktarım değerlendirilir | Bölge, hesap ayrılığı, Object Lock/immutability, erişim logu, lifecycle ve geri dönüş tatbikatı doğrulanır |
| Operasyon uyarı webhook'u | Servis adı ve hata özeti; iş/müşteri verisi gönderilmez | Slack/Teams veya eşdeğeri **henüz seçilmedi** | Sağlayıcı ülkesine göre değerlendirilir | Mesaj içeriği yalnız teknik özetle sınırlandırılır; webhook sır olarak saklanır |

Yurt dışı aktarım için uygun güvence kurulmadan ilgili servis etkinleştirilmez.
Standart sözleşme yöntemi kullanılırsa imzadan sonraki **5 iş günü içinde** Kişisel
Verileri Koruma Kurumu'na bildirim yükümlülüğü hukuk danışmanıyla takip edilir.
Sağlayıcı, bölge veya alt işleyen değişikliği bu tabloyu ve hukuki değerlendirmeyi
yeniden açar.
