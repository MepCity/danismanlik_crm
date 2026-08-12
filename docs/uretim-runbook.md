# Üretim kurulum ve işletim runbook'u

Bu runbook ofiste 7/24 çalışan, adanmış Linux makine içindir. Komutlar repo
kökünde çalıştırılır. Üretim compose komutlarının tamamında aynı dosyalar
kullanılır:

```bash
docker compose --env-file .env.production -f compose.prod.yaml
```

## 1. Canlıya çıkış kapıları

Aşağıdaki altı madde imzalı kontrol listesinde tamamlanmadan canlıya çıkılmaz:

- [ ] UPS takılı; yük altında çalışma süresi ölçüldü ve işletim sistemi düzgün
  kapanma olayı gerçek elektrik kesme tatbikatıyla doğrulandı.
- [ ] Şifreli yedek ofis dışındaki ayrı hesaba gidiyor; geri dönüş tatbikatı
  başarıyla tamamlandı.
- [ ] Modem/NAT üzerinde port yönlendirme yok; üretim compose çıktısında host
  portu yok; erişim yalnız Cloudflare Tunnel üzerinden.
- [ ] Makine adanmış; günlük kullanıcı hesabı ve kontrolsüz otomatik yeniden
  başlatma yok.
- [ ] İki fiziksel disk RAID-1/mirror olarak sağlıklı veya yazılı risk kabulüyle
  saatlik off-site RPO uygulanıyor. RAID yedek değildir.
- [ ] Makine kilitli oda/dolapta; fiziksel erişim listesi ve giriş kaydı var.

Ek kapılar: KVKK envanterindeki “canlıyı bloke eder” satırları doldurulur, alan
adı doğrulanır, sağlayıcı sözleşmeleri onaylanır ve internet kesintisi riski
yazılı kabul edilir.

## 2. Sıfırdan ilk kurulum

1. Desteklenen 64-bit Linux kurun; disk şifrelemesini, saat eşitlemesini ve
   otomatik güvenlik yamalarını açın. Docker Engine ile Compose eklentisini
   üreticinin resmi deposundan kurun. Operatörü ayrı, parolalı hesaba alın.
2. BIOS'ta güç geri geldiğinde açılmayı ayarlayın. UPS üretici servisini,
   düşük pilde `shutdown` tetikleyecek şekilde kurun ve kapatma tatbikatını yapın.
3. Host güvenlik duvarında varsayılan gelen trafiği reddedin. SSH gerekiyorsa
   yalnız yönetim VLAN'ı/Tailscale üzerinden izin verin. Modemde hiçbir port
   yönlendirmesi oluşturmayın. `ss -lntup` çıktısını kayıt altına alın.
4. Repoyu yalnız dağıtım hesabıyla klonlayın, doğrulanmış sürüme geçin. Git
   kullanıcısı uygulama verisi volume'larına erişmemelidir.
5. `cp .env.production.example .env.production` yapın; dosyayı `chmod 600` ile
   sınırlandırın. Dosya repo dışında, şifreli sır yöneticisindeki değerlerle
   doldurulur.
6. `APP_KEY` için `base64:` önekli 32 bayt OpenSSL CSPRNG çıktısı,
   DB/Redis/yedek DB/restic parolaları için en az 32 bayt CSPRNG çıktı üretin. Cloudflare
   token, SMTP/S3 anahtarları, Sentry DSN ve webhook ilgili sağlayıcıdan alınır.
   Sırlar iki yetkilinin erişebildiği kurumsal parola kasasında tutulur;
   sohbet/e-posta/commit içinde paylaşılmaz.
7. `./scripts/production-preflight.sh .env.production` çalıştırın. Bu kontrolün
   boş sırda, `APP_DEBUG=true`, HTTP URL'de, yerel MinIO endpoint'inde ve host
   portu eklendiğinde kırmızıya düştüğünü ilk kurulum kaydına ekleyin.
8. `docker compose --env-file .env.production -f compose.prod.yaml build app web backup`
   ile prod hedefini gerçek makinede derleyin.
9. Çekirdek servisleri başlatın:

   ```bash
   docker compose --env-file .env.production -f compose.prod.yaml up -d db redis clamav app web
   docker compose --env-file .env.production -f compose.prod.yaml exec app php artisan migrate --force
   docker compose --env-file .env.production -f compose.prod.yaml exec app php artisan db:seed --class=ReferenceDataSeeder --force
   docker compose --env-file .env.production -f compose.prod.yaml exec app php artisan audit:ensure-partitions --months=3
   docker compose --env-file .env.production -f compose.prod.yaml up -d queue scheduler
   ```

10. Cloudflare Zero Trust hesabında alan adını ekleyin. Networks → Tunnels altında
    named tunnel oluşturun, Docker ortamını seçin ve token'ı yalnız sır kasasına
    kaydedin. Public Hostname'i `http://web:80` servisine bağlayın; HTTP→HTTPS,
    HSTS ve TLS 1.3 açın. Access politikası uygulanacaksa kurumsal kullanıcı
    grubunu tanımlayın. `--profile edge up -d tunnel` ile başlatın.
11. `--profile operations up -d backup object-replica monitor` ile yedek,
    evrak replikasyonu ve izlemeyi başlatın. Kaynak ve replikasyon S3 kovalarında
    private access, versioning ve server-side encryption değerlerini sağlayıcı
    API/CLI çıktısıyla doğrulayın. İki hedef farklı hesap/kimlik bilgisi kullanır;
    hedefteki bir örnek nesnenin SHA-256 değerini kaynakla karşılaştırın.
12. Dış ağdaki bir cihazdan `https://alan-adi/up` isteğinin `200` döndüğünü ve
    oturum çerezinin `Secure`, `HttpOnly`, `SameSite=Lax` olduğunu doğrulayın.
    Host üzerinde `docker compose ... ps --format json` çıktısındaki
    `Publishers` dizileri boş olmalıdır.

Cloudflare, genel tarayıcı erişimi için seçildi. Tailscale yalnız yönetim erişimi
için eklenebilir; son kullanıcı cihazlarına istemci kurma zorunluluğu nedeniyle
ana uygulama tüneli değildir.

## 3. Güncelleme

1. Güncelleme penceresini duyurun; çalışan sürüm etiketi ile DB yedek snapshot
   kimliğini kaydedin. `backup-full.sh` komutunu tek seferlik çalıştırıp başarılı
   snapshot'ı doğrulayın.
2. İmzalı sürümü çekin ve `APP_IMAGE_TAG` değerini değişmez sürüm etiketine
   ayarlayın. İmajları build edin.
3. Bakım modunu açın, migration'ı ayrı komutla çalıştırın, servisleri yeniden
   oluşturun ve kuyruk işçilerini yeniden başlatın:

   ```bash
   docker compose --env-file .env.production -f compose.prod.yaml exec app php artisan down --retry=60
   docker compose --env-file .env.production -f compose.prod.yaml build app web
   docker compose --env-file .env.production -f compose.prod.yaml run --rm app php artisan migrate --force
   docker compose --env-file .env.production -f compose.prod.yaml up -d app web queue scheduler
   docker compose --env-file .env.production -f compose.prod.yaml exec app php artisan up
   ```

4. `/up`, giriş, kuyruk, evrak yükleme/tarama ve bir rapor için kısa duman testi
   yapın. Sentry ve operasyon uyarı kanalında yeni hata olmadığını kontrol edin.

## 4. Bozuk sürümden geri alma

Uygulama hatalı, migration geriye uyumluysa `APP_IMAGE_TAG` önceki etikete alınır
ve `up -d app web queue scheduler` çalıştırılır. Kod checkout'u önceki imajı
yeniden üretmek için kullanılabilir; çalışan DB volume'u silinmez.

Migration geriye uyumsuzsa yalnız kodu geri almak güvenli değildir. Bakım modu
açılır, bütün yazıcı servisler (`app`, `queue`, `scheduler`) durdurulur ve sürüm
öncesi yedeğe aşağıdaki felaket dönüşü uygulanır. Migration `down()` ancak sürüm
runbook'unda veri kaybetmediği açıkça kanıtlanmışsa kullanılabilir.

Hiçbir durumda `docker compose down -v`, volume silme, `git reset --hard` veya
canlı DB üzerinde `migrate:fresh` çalıştırılmaz.

## 5. Yedek ve nokta-zaman geri dönüş

`db`, tamamlanmış WAL dosyalarını `wal_archive` volume'una atomik taşır. `backup`
servisi WAL'ı en çok 60 saniyede bir, fiziksel taban yedeğini günde bir `restic`
deposuna yollar. Şifreleme ofis makinesinde, aktarım öncesinde yapılır. Uzak
başarıdan önce yerel WAL silinmez; hata webhook'a bildirilir.

Canlı geri dönüş öncesi olay zamanı UTC olarak belirlenir. Canlı servisler
durdurulur, mevcut volume silinmeden salt okunur/erişilemez duruma alınır ve yeni
boş volume hazırlanır. Önce tatbikat komutlarıyla doğrulama yapılır; onaylanan
volume ancak sonra üretim DB hizmetine bağlanır.

### Aylık geri dönüş tatbikatı

Tatbikat canlı `pgdata` volume'una dokunmaz:

```bash
docker compose --env-file .env.production -f compose.prod.yaml -f compose.restore-drill.yaml run --rm restore
docker compose --env-file .env.production -f compose.prod.yaml -f compose.restore-drill.yaml up -d restore-db
docker compose --env-file .env.production -f compose.prod.yaml -f compose.restore-drill.yaml exec restore-db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select count(*) from migrations;"'
docker compose --env-file .env.production -f compose.prod.yaml -f compose.restore-drill.yaml stop restore-db
```

Belirli UTC ana dönmek için `restore` komutuna ikinci argüman olarak ISO-8601
zamanı verilir. Sonuçta migrations, kritik tablo sayıları, son audit kaydı ve
rastgele örnek dosyaların S3 `sha256` değerleri doğrulanır. Tatbikat tarihi,
snapshot kimliği, hedef zaman, süre ve operatör `docs/geri-donus-tatbikati.md`
formatında kaydedilir. Tatbikat volume'u sonraki ay yeniden kullanılmaz; silme
işlemi hedef doğrulaması ve ikinci operatör onayıyla bakım prosedüründe yapılır.

## 6. İzleme ve rutin bakım

- Her 5 dakika: DB disk doluluğu (%80), Redis kuyruk uzunluğu (100), son tam
  yedek yaşı (30 saat), son başarılı evrak replikasyonu (15 dakika) ve gelecek
  ay `audit_log_YYYYMM` partition'ı kontrol edilir.
- Her deploy: `/up`, Sentry test olayı, ClamAV EICAR ve dış tünel testi.
- Haftalık: `docker compose ps`, servis restart sayıları, ClamAV imza güncelliği,
  S3 replication/versioning, SMART/RAID ve UPS olay kayıtları.
- Aylık: boş volume'a geri dönüş tatbikatı; işletim sistemi/Docker güvenlik
  yamaları; disk sıcaklığı/SMART; kullanıcı ve fiziksel erişim gözden geçirmesi.
- Yıllık: felaket kurtarma ve sızma testi.

Scheduler'da `audit:ensure-partitions` her gün 00:15'te çalışır. Üretimde şu üç
kanıt birlikte alınır: `php artisan schedule:list`, komutun elle başarılı
çalışması ve `pg_tables` içinde gelecek üç ay partition'larının sorgu çıktısı.

## 7. Sır olayı

Bir sır yanlışlıkla commit/push edilirse commit'i geri almak yeterli değildir.
İlgili APP_KEY/DB/Redis/S3/SMTP/tünel/Sentry/webhook/restic değeri derhal iptal
edilir ve yenilenir; erişim logları incelenir, olay kaydı açılır, etkilenen veri
değerlendirilir. Git geçmişi ayrıca temizlense bile eski sır tekrar kullanılmaz.
APP_KEY rotasyonu şifreli uygulama verisinin etkisini değerlendirmeden yapılmaz;
restic parolası rotasyonu yeni repository ve doğrulanmış geri dönüş gerektirir.
