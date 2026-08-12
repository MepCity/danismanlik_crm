# ADR-0024: Üretim tüneli, yedek ve belge deposu

## Durum

Kabul edildi

## Bağlam

PLAN.md K-06 ve K-10; ofis makinesinde üretim için modem portu açılmamasını,
belge deposunun uygulama diskinden ayrı olmasını ve günlük şifreli ofis dışı
yedek ile PostgreSQL WAL arşivini zorunlu tutar. Sistemin yaklaşık 15 iç
kullanıcısı vardır; kullanıcı cihazlarının tek tek özel ağa alınması operasyonel
sürtünme yaratır.

## Karar

Kamuya açık uygulama erişimi için **Cloudflare Tunnel** kullanılır. `cloudflared`
compose ağı içindeki HTTP Nginx'e yalnız giden bağlantı kurar; compose dosyasında
host `ports` alanı yoktur. TLS Cloudflare'da sonlanır. Uygulama güvenilen proxy
başlıklarını işler ve üretimde HTTPS URL üretimini zorlar.

Belge deposu olarak ofis makinesindeki MinIO değil, **yönetilen S3 uyumlu nesne
deposu** kullanılır. Kova özel, sürümleme ve sunucu tarafı şifreleme açık olur.
`object-replica` servisi evrakları ayrı kimlik bilgilerine sahip ikinci bir
S3 hedefe beş dakikada bir kopyalar; kaynak silmelerini hedefe yaymaz.
Sağlayıcı ve bölge KVKK alt işleyen envanterinde canlı öncesinde doldurulur;
uygun aktarım güvencesi yoksa Türkiye dışındaki bölge seçilemez.

PostgreSQL için günlük fiziksel `pg_basebackup` ve en çok 60 saniye gecikmeli WAL
arşivi alınır. `restic` her iki veri kümesini ofis dışındaki ayrı S3 hesabına
göndermeden önce istemci tarafında şifreler. Tam yedekler 14 günlük ve 12 aylık
saklama politikasına tabidir. WAL yerel aktarım alanından yalnız uzak yedek
başarılı olduktan sonra silinir. Aylık geri dönüş tatbikatı ayrı, boş bir Docker
volume'ünde yapılır; canlı volume üzerine geri dönüş yapılmaz.

## Sonuçlar

- Modem port yönlendirmesi ve host port yayını yoktur.
- Tailscale istemcisi kurmadan tarayıcı erişimi sağlanır.
- Uygulama/DB diski veya bir S3 hesabı kaybolsa da evrak kopyası ve şifreli DB
  yedeği ayrı ofis dışı hedeflerde kalır.
- Cloudflare, S3, yedek ve Sentry/SMTP sağlayıcıları KVKK açısından alt işleyen
  veya yurt dışı aktarım noktası olabilir; envanter ve hukuki güvence canlı
  öncesi kapıdır.
- `restic` parolası kaybolursa yedek geri getirilemez; iki yetkili tarafından
  çevrimdışı saklanması zorunludur.

## Alternatifler

Tailscale, her kullanıcı cihazında istemci ve kullanıcı yaşam döngüsü yönetimi
gerektirdiği için seçilmedi. Yerel MinIO + ikinci sağlayıcı replikasyonu mümkün
olsa da aynı ofis makinesinde ek servis ve replikasyon takibi doğurduğu için
seçilmedi. Yalnız mantıksal `pg_dump`, nokta-zaman geri dönüşü sağlamadığı için
tek yedek yöntemi olarak reddedildi.
