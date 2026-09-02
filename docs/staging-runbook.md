# Bizlife CRM — Staging / Pilot Ortamı İşletim Rehberi (Runbook)

Bu belge, Bizlife CRM'nin geçici staging / pilot ortamının kurulumu, dağıtımı, güvenli demo veri provizyonu, izlenmesi, güncellenmesi ve pilot süreci tamamlandığında güvenli biçimde kapatılması için operasyon adımlarını içerir.

---

## 1. Mimari Genel Bakış

Staging ortamı, `compose.staging.yaml` ile yönetilen izole bir mikro servis grubudur:
- **`web`**: Nginx (statik varlıklar, TLS sonlandırma/proxy, güvenlik başlıkları). İnternete açık port barındırmaz.
- **`app`**: PHP 8.4+ FPM (`APP_ENV=staging`, `APP_DEBUG=false`, OPcache devrede).
- **`db`**: PostgreSQL 17 (SCRAM-SHA-256 kimlik doğrulama).
- **`redis`**: Redis 7 (şifreli oturum, önbellek ve asenkron kuyruk yönetimi).
- **`clamav`**: Yüklenen evrakların gerçek zamanlı güvenlik/antivirüs taraması.
- **`queue` & `scheduler`**: Asenkron bildirimler, e-posta kuyrukları ve periyodik görevler.
- **`tunnel`**: Cloudflare Named Tunnel (`cloudflared`). Sunucunun dışarıya hiçbir gelen port (80/443/5432) açmadan yalnızca tünel üzerinden güvenli HTTPS erişimi sağlamasını temin eder.

---

## 2. Gerekli Çevre Değişkenleri ve Sırlar (`.env.staging`)

Staging sunucusunda `.env.staging.example` dosyasından `.env.staging` türetilir:

```bash
cp .env.staging.example .env.staging
```

Aşağıdaki zorunlu sırlar güçlü rastgele değerlerle doldurulmalıdır:

| Değişken | Açıklama | Güvenlik Kriteri |
|---|---|---|
| `APP_KEY` | Laravel şifreleme anahtarı | `php artisan key:generate --show` |
| `DB_PASSWORD` | PostgreSQL staging kullanıcısı parolası | Rastgele ≥ 24 karakter |
| `REDIS_PASSWORD` | Redis kimlik doğrulama parolası | Rastgele ≥ 24 karakter |
| `CLOUDFLARE_TUNNEL_TOKEN` | Cloudflare Zero Trust tünel belirteci | Cloudflare Dashboard'dan temin edilir |
| `STAGING_MARKETING_EMAIL` | Pilot Pazarlama kullanıcısı e-postası | Geçerli e-posta formatı |
| `STAGING_MARKETING_PASSWORD` | Pilot Pazarlama parolası | En az 12 karakter |
| `STAGING_PM_EMAIL` | Pilot Proje Yöneticisi e-postası | Geçerli e-posta formatı |
| `STAGING_PM_PASSWORD` | Pilot Proje Yöneticisi parolası | En az 12 karakter |
| `STAGING_COMPANY_AUTHORITY_EMAIL` | Pilot Şirket Yetkilisi e-postası | Geçerli e-posta formatı |
| `STAGING_COMPANY_AUTHORITY_PASSWORD` | Pilot Şirket Yetkilisi parolası | En az 12 karakter |
| `STAGING_SYSTEM_ADMIN_EMAIL` | Pilot Sistem Yöneticisi e-postası | Geçerli e-posta formatı |
| `STAGING_SYSTEM_ADMIN_PASSWORD` | Pilot Sistem Yöneticisi parolası | En az 12 karakter |

> [!WARNING]
> Şifreler veya sırlar asla kaynak kod deposuna commit edilmez. `DemoDataSeeder` staging ortamında çalıştırılamaz; pilot kullanıcıları ve verileri `system:provision-staging-demo` komutu ile kurulur.

---

## 3. İlk Kurulum ve Yayına Alma

```bash
# 1. Konteyner imajlarını derleyin ve arka planda başlatın
docker compose -f compose.staging.yaml up -d --build

# 2. Konteyner durumlarını doğrulayın
docker compose -f compose.staging.yaml ps

# 3. Veritabanı şemasını oluşturun
docker compose -f compose.staging.yaml exec app php artisan migrate --force

# 4. Güvenli pilot demo kullanıcılarını ve kurgusal verileri kurun
docker compose -f compose.staging.yaml exec app php artisan system:provision-staging-demo
```

---

## 4. Sağlık ve Güvenlik Kontrolleri

Yayına alma sonrasında aşağıdaki doğrulamaları gerçekleştirin:

1. **Readiness Kontrolü:**
   ```bash
   curl -i https://<staging-domain>/health
   ```
   *Beklenen yanıt:* HTTP 200 `{"status":"ok","services":{"database":"ok","redis":"ok","storage":"ok"}}`

2. **Liveness Kontrolü:**
   ```bash
   curl -i https://<staging-domain>/up
   ```
   *Beklenen yanıt:* HTTP 200

3. **Arama Motoru İndeksleme Engeli:**
   ```bash
   curl -i https://<staging-domain>/robots.txt
   ```
   *Beklenen yanıt:* HTTP 200 `User-agent: *\nDisallow: /` ve yanıtlarda `X-Robots-Tag: noindex, nofollow, noarchive` başlığı.

4. **Staging Uyarı Başlığı:**
   Tarayıcıdan `/operasyon` paneline girildiğinde ekranın üst kısmında `"Test ortamı — gerçek müşteri veya evrak verisi girmeyin."` uyarı bandının görüntülendiğini teyit edin.

---

## 5. Pilot Kullanıcı Rolleri ve Test Matrisi

Pilot ortamında 4 ayrı rol izole biçimde test edilir:

| Rol | Kapsam (`data_scope`) | Görev ve Erişim Alanı |
|---|---|---|
| **Pazarlama** | `own` | Firma rehberi, arama listeleri, lead yönetimi. |
| **Proje Yöneticisi** | `team` | Takımına atanan dosyalar, evrak toplama, checklist oluşturma, statü ilerletme. |
| **Şirket Yetkilisi** | `all` | Şirket genel bakış paneli, tüm dosyaların durumu, raporlar ve Excel dışa aktarım. |
| **Sistem Yöneticisi** | `none` | Yalnızca Kullanıcılar, Takımlar, Roller/İzinler ve Sistem Ayarları. |

---

## 6. Log İzleme ve Sorun Giderme

```bash
# Bütün servis loglarını canlı izleme
docker compose -f compose.staging.yaml logs -f --tail=100

# Yalnızca uygulama ve kuyruk logları
docker compose -f compose.staging.yaml logs -f app queue
```

---

## 7. Güncelleme ve Geri Alma (Rollback)

### Güncelleme:
```bash
git pull origin main
docker compose -f compose.staging.yaml build app web
docker compose -f compose.staging.yaml exec app php artisan migrate --force
docker compose -f compose.staging.yaml up -d --no-deps app web queue scheduler
```

### Geri Alma:
```bash
docker compose -f compose.staging.yaml exec app php artisan migrate:rollback --step=1
```

---

## 8. Pilot Tamamlandığında Güvenli Kapatma ve Veri İmhası

Pilot süreci bittiğinde staging sunucusundaki verilerin tamamen yok edilmesi için:

```bash
# Konteynerleri ve veritabanı/dosya volume'lerini tamamen silin
docker compose -f compose.staging.yaml down -v --remove-orphans

# Staging çevre değişkenleri dosyasını güvenli biçimde silin
shred -u .env.staging || rm -f .env.staging
```
