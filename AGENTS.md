# AGENTS.md — bu depoda çalışan her agent için bağlayıcı kurallar

> **Her iş paketi promptu bu dosyayla başlar.** Agent önce bunu ve `PLAN.md`'yi okur, sonra kendisine verilen paketin işine geçer.
>
> İş paketi tanımları depoda tutulmaz — sohbette verilir. Depoda yalnızca kalıcı iki belge durur: `PLAN.md` (kararlar) ve bu dosya (kurallar).

---

## Proje nedir

Teşvik/hibe danışmanlığı yapan bir şirket için **dar kapsamlı operasyon sistemi**. Genel CRM değil.

Akış: pazarlama firmayı arar → iş alınır → şirket yetkilisi bir proje yöneticisine atar → PM programa özgü evrakları toplar → başvuru kuruma yapılır → onay/ret.

Müşteriye daha önce ERPNext sunuldu, **"çok kapsamlı" diye reddedildi**. Bu projenin bir numaralı riski kapsam kaymasıdır.

Tam bağlam: repo kökündeki **`PLAN.md`** — kararlar (ADR K-01…K-11), veri modeli, statü mimarisi, kapsam sınırı.

---

## Teknoloji (sabit, tartışmaya açık değil)

| | |
|---|---|
| PHP | 8.4+ |
| Framework | Laravel **13.24** |
| Panel | Filament **5.7** |
| Veritabanı | PostgreSQL **17** |
| Kuyruk / önbellek | Redis |
| Nesne depolama | S3 uyumlu — geliştirmede MinIO container |
| Çalıştırma | Docker Compose |
| Test | Pest |
| Statik analiz | Larastan |
| Biçimlendirme | Laravel Pint |

**Yereldeki PHP kullanılmaz — bozuk (`php@7.1`).** Bütün `php`, `composer`, `artisan`, `pest` komutları container içinde çalışır:

```bash
docker compose exec app php artisan ...
docker compose run --rm composer install
```

Yeni paket eklemeden önce **gerekliliğini gerekçelendir.** Bağımlılık listesi kısa kalmalı.

---

## Mimari kurallar — ihlal edilirse paket reddedilir

### 1. İş mantığı controller'da ya da Filament Resource'ta durmaz

```
app/
  Domain/            ← iş kuralları burada
    Deal/
      Services/StatusMachine.php
      Services/ChecklistGenerator.php
      Events/DealStatusChanged.php
    Document/
    Crm/
  Models/
  Filament/          ← yalnızca sunum: form, tablo, aksiyon tanımı
  Http/
```

Filament Resource ve controller **yalnızca servis çağırır**. Bir Resource dosyasında `if` ile iş kuralı görürsem paket geri döner. Sebep: mobil uygulama ve müşteri portalı aynı servisleri çağıracak (PLAN.md §K-03 "değişmez kural").

### 2. Statüler, izinler, evrak listeleri koda gömülmez

Hepsi veritabanı satırı. Kodda `if ($deal->status === 'evrak_bekleniyor')` **yasak**. Statü davranışı `transitions` tablosundan okunur.

Tek istisna: sistemin bilmek zorunda olduğu birkaç anlamsal bayrak (`statuses.is_terminal` gibi) — o da kolon olarak, string karşılaştırmasıyla değil.

### 3. Denetim kaydı iki katmandır

- **Uygulama katmanı** (`activities`): kullanıcıya gösterilen okunabilir olay akışı. Ham JSON değil, olay tipi + parametreler + **o anki etiket anlık görüntüsü**.
- **Veritabanı katmanı** (`audit_log`): trigger tabanlı, JSONB delta, salt-ekleme.

Trigger uygulama kullanıcısını **kendiliğinden bilmez**. Her transaction'da `SET LOCAL app.actor_id / app.session_id / app.client_ip / app.source` yazılır (middleware otomatik yapar, geliştiricinin hatırlamasına bırakılmaz). Değer yoksa kayıt `system` / `unknown` olur.

**Denetim JSON'una asla girmeyecekler:** parola hash'i, oturum ve API token'ları, 2FA gizli anahtarı, imzalı URL secret'ları.

### 4. Kayıt silinmez

Statü, geçiş, evrak gereksinimi, izin kaydı — hiçbiri `DELETE` edilmez. `is_active = false` ya da durum değişikliği. Geçmişin okunabilirliği buna bağlı.

### 5. Yetki sunucu tarafında, varsayılan reddet

Policy + `own | team | all` kapsamı. Arayüzde butonu gizlemek yetki değildir. **Her yetki kuralının Pest testi olur.**

### 6. Kişisel veri

İzinler `communication_consents` tablosunda **append-only** tutulur — satır güncellenmez, yeni satır eklenir. `contacts` üzerindeki izin alanları yalnızca hızlı sorgu için denormalize özettir.

---

## Tasarım sistemi (arayüz paketleri için bağlayıcı)

Referans: **Linear · Attio · Twenty CRM**. **ERPNext'in görsel dili referans değildir** — müşteri onu zaten reddetti.

- Tek vurgu rengi. Durum renkleri (bekliyor / tamam / gecikmiş / iptal) ayrı bir eksen, vurgu rengi sayılmaz.
- Nötr gri skala hafif mavi eğilimli seçilir; saf gri kullanılmaz.
- **Gölge değil kenarlık.** 1px kenarlık + arka plan tonu.
- Operasyonel yoğunluk: bir ekranda 25–30 satır görünmeli.
- Durum renkle **ve** formla kodlanır (rozet, şerit, ikon).
- Sayı sütunlarında `tabular-nums`.
- Açık ve koyu tema ayrı ayrı tasarlanır.
- **Çiğ hex kodu yazılmaz** — her renk WP-14'teki token dosyasından gelir.

---

## Git akışı — her paket için zorunlu

**Remote:** `https://github.com/MepCity/danismanlik_crm.git`
**Hesap:** GitHub CLI zaten `MepCity` olarak oturum açmış (`gh auth status` ile doğrula). Ek kimlik bilgisi girmene gerek yok.

```bash
git config user.name  "MepCity"
git config user.email "hamasetyasir@gmail.com"
```

**Dal stratejisi:** her iş paketi **kendi dalında**, `main`'e doğrudan push yok.

```
main                     ← korumalı, sadece PR ile
  └── wp-01-docker-iskelet
  └── wp-02-kod-kalite
  └── ...
```

**Commit mesajı — Conventional Commits, kısa ve öz:**

```
feat(docker): geliştirme ortamı ve Laravel iskeleti
```

Kurallar:

- **Başlık ≤ 50 karakter**, küçük harfle başlar, sonunda nokta yok, emir kipi.
- Tip önekleri: `feat` `fix` `chore` `docs` `test` `refactor` `perf` `ci`. Kapsam parantez içinde (`docker`, `db`, `workflow`, `ui`…).
- **Gövde yalnızca "neden" başlıktan anlaşılmıyorsa yazılır.** Yazılırsa Türkçe, en fazla birkaç satır. Yaptığın işi tekrar anlatma — diff zaten orada.
- 🔴 **Commit mesajına kendini yazma.** `Co-Authored-By`, `Generated with …`, model adı, araç adı, emoji imzası — hiçbiri olmayacak. Mesajda **sadece commit mesajı** bulunacak.

Aynı kural PR açıklamaları için de geçerli: araç/model imzası eklenmez.

**Paket bitince:**

1. Dalı push et: `git push -u origin wp-XX-...`
2. PR aç: `gh pr create --fill` — açıklamada paketin kabul kriterlerini **işaretlenmiş liste** olarak koy, hangilerinin doğrulandığını göster.
3. **Merge etme.** İnceleme bekle.

**Atomik commit.** "Her şey tek commit" kabul edilmez; mantıksal adımlar ayrı commit olur (migration ayrı, servis ayrı, test ayrı).

### 🔴 Public repo — bunlar asla commit edilmeyecek

Depo şu an **herkese açık**. Bu yüzden:

- **Gerçek müşteri/firma verisi yok.** Seeder'lar tamamen uydurma veri kullanır — gerçek vergi numarası, gerçek firma unvanı, gerçek kişi adı ya da telefon **hiçbir yerde geçmez**. KVKK gereği.
- **Hiçbir sır commit edilmez:** `.env`, API anahtarı, parola, token, sertifika, imzalama anahtarı. Sadece `.env.example` (içi boş/örnek değerlerle).
- Yanlışlıkla bir sır push edilirse **o sır yanmıştır**: commit'i geri almak yetmez, anahtar/parola derhal iptal edilip yenilenir.
- Müşteri adı, sözleşme detayı, fiyat bilgisi kod deposuna girmez.

---

## Her paketin uyacağı teslim kuralları

1. **Kapsam dışına çıkma.** Paket "şu 4 tabloyu oluştur" diyorsa 5. tabloyu ekleme. Eksik gördüğün şeyi `NOTLAR.md`'ye yaz, yapma.
2. **Test yaz.** Migration paketleri hariç her pakette Pest testi olur. Yetki ve statü geçişi testleri zorunlu.
3. **`docker compose exec app ./vendor/bin/pint`** ve **`larastan`** temiz geçecek.
4. **Türkçe arayüz, İngilizce kod.** Tablo/kolon/sınıf/değişken adları İngilizce; kullanıcıya görünen her metin Türkçe ve dil dosyasında (`lang/tr/`), koda gömülü değil.
5. **Migration geri alınabilir olacak** (`down()` çalışacak).
6. Teslimde kısa bir özet: ne yapıldı, hangi kararlar verildi, ne yapılmadı ve neden.

---

## Kapsam filtresi

Yeni bir fikir aklına geldiğinde:

- **İş özelliği mi?** → "Bu, bir dosyanın hangi statüde olduğunu ya da hangi evrağın eksik olduğunu göstermeye yarıyor mu?" Hayırsa **yapma**, not düş.
- **Zorunlu kalite/güvenlik/mevzuat gereği mi?** (yedek, denetim kaydı, yetki, şifreleme, KVKK alanı) → Bu filtreye tabi değil, kapsam içidir.

---

## Paket haritası

| # | Paket | Bağımlılık |
|---|---|---|
| **A. Temel** | | |
| WP-01 | Docker geliştirme ortamı + Laravel iskeleti | — |
| WP-02 | Kod kalite altyapısı (Pint, Larastan, Pest, CI) | WP-01 |
| WP-03 | Mimari iskelet (modül sınırları, servis katmanı, olay/outbox) | WP-02 |
| **B. Veri** | | |
| WP-04 | Migration: kimlik & organizasyon | WP-03 |
| WP-05 | Migration: CRM çekirdeği + izin defteri | WP-04 |
| WP-06 | Migration: program, evrak şablonu, dosya, files | WP-05 |
| WP-07 | Migration: iş akışı, denetim, trigger session context | WP-06 |
| WP-08 | Seeder'lar (statü, rol, gerçek program + evrak listesi) | WP-07 |
| **C. Domain** | | |
| WP-09 | StatusMachine (guard / condition / effect) | WP-08 |
| WP-10 | ChecklistGenerator + koşul motoru | WP-09 |
| WP-11 | DocumentService (yükleme, sürüm, geçerlilik) | WP-10 |
| WP-12 | Yetkilendirme (policy, kapsam, break-glass) | WP-09 |
| WP-13 | Aktivite/denetim uygulama katmanı | WP-09 |
| **D. Arayüz** | | |
| WP-14 | Filament paneli + tasarım sistemi (token, tema) | WP-12 |
| WP-15 | Yönetim ekranları (program, şablon, statü, kullanıcı) | WP-14 |
| WP-16 | Dosya panosu + dosya detayı + evrak checklist | WP-14, WP-11 |
| WP-17 | Pazarlama ekranları (arama listesi, lead panosu) | WP-14 |
| WP-18 | Zaman tüneli + yorum bileşeni | WP-13, WP-16 |
| WP-19 | Gösterge paneli + raporlar + Excel | WP-16 |
| **E. Operasyon** | | |
| WP-20 | Üretim kurulumu (tünel, yedek, restore tatbikatı) | WP-19 |
| WP-21 | Kabul testi senaryoları | WP-20 |
