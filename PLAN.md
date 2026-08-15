# Bizlife CRM — Proje Planı

> **Durum:** Ana operasyon akışı uygulanıyor
> **Sürüm:** 1.15 — 16.08.2026
> **Teknik fizibilite raporu:** https://claude.ai/code/artifact/2e291308-4cd0-4696-8747-99e93095aa51

---

## 0. Bu belge ne işe yarar

Bu, projenin **tek kaynaklı hafızası**. Alınan kararlar, kararların gerekçeleri, reddedilen alternatifler ve nedenleri burada. Üç ayrı değerlendirme turunun sonucu; her turda neyin değiştiği §2'de.

Kod yazılmaya başlandığında bu belge donmaz — karar değişirse §12 Değişiklik Günlüğü'ne satır eklenir, ilgili bölüm güncellenir. "Neden böyle yapmışız?" sorusunun cevabı hep burada olmalı.

---

## 1. Problem ve bağlam

### 1.1 Müşteri kim

Teşvik/hibe danışmanlığı yapan bir şirket. Firmalara KOSGEB, TÜBİTAK, Yatırım Teşvik Belgesi, Kalkınma Ajansı gibi destek programlarına başvuru sürecinde danışmanlık veriyor.

### 1.2 Gerçek iş akışı

1. **Pazarlama ekibi** firmaları arar, ilgilenenleri sisteme kaydeder.
2. İş alınırsa **şirket yetkilisi** dosyayı bir **proje yöneticisine (PM)** atar.
3. PM firmayı arar, başvurulacak programa özgü **evrakları** ister.
4. Evraklar toplanır, kontrol edilir, eksikler tamamlanır.
5. Başvuru kuruma yapılır, sonuç beklenir, onay/ret/revizyon gelir.

**Kayıt granülerliği:** Firma ana kayıttır; statü firmaya verilmez. Her program ilgisi ayrı bir `lead`, alınan her iş ayrı bir `deal` olur. Aynı firma aynı veya farklı programlarda, eş zamanlı ya da farklı tarihlerde sınırsız sayıda bağımsız fırsat ve proje taşıyabilir. Görüşülen kişi fırsata ve her görüşme satırına açıkça bağlanır; kişi için şirket içindeki unvan yeterlidir. Firma geneli yorum/görevler firma kaydında, belirli bir sürece ait olanlar ilgili fırsat veya projede kalır.

### 1.3 Müşterinin açık talepleri

| # | Talep | Karşılığı |
|---|---|---|
| 1 | Müşteri kaydı ve statü takibi | §5 Statü makinesi |
| 2 | İş alınınca PM'e atama, PM kendi sayfasında görsün | §5.2, §7 Yetki |
| 3 | Programa göre değişen evrak listesi | §6 Evrak şablon motoru |
| 4 | Yeni program açılınca (ör. Yeşil Sanayi → fizibilite raporu) evrak listesi kod yazmadan eklenebilsin | §6.2 |
| 5 | Her işlemin ve işlemi yapanın okunabilir geçmişi (Jira gibi) | §8 Aktivite geçmişi |
| 6 | Her işin altında yorum alanı | §8.3 |
| 7 | Herkes kendi e-posta/şifresiyle girsin, sadece yetkili olduğunu görsün | §7 Yetkilendirme |
| 8 | İleride e-TUYS entegrasyonu, mobil uygulama | §9 Entegrasyon, §10 Mobil |

### 1.4 Kritik kısıt

Müşteriye daha önce **ERPNext sunuldu ve reddedildi**: "çok kapsamlı, ben sadece kendi ihtiyacım olan şeyleri istiyorum."

Bu ret **teknik bir itiraz değil, bilişsel yük itirazı**. Muhasebe/stok/üretim/İK istemiyor; satıştan teşvik dosyasının sonuçlanmasına kadar olan kendi akışını yöneten sade bir sistem istiyor.

> **Projenin bir numaralı riski kapsam kaymasıdır.** ERPNext'i reddeden müşteriye, iki yıl içinde küçük ERPNext yapmak en olası başarısızlık senaryosu.

---

## 2. Karar geçmişi

### Tur 1 — İlk teknik fizibilite (Claude)

Web araştırması + domain analizi ile hazırlanan rapor. Ana çıktılar: web+PWA, sıfırdan dar kapsam, Laravel+Filament önerisi, 12 tablolu veri modeli, DB tabanlı statü makinesi, program sürümleme, e-TUYS'ta API olmadığı tespiti, 7–9 haftalık takvim.

### Tur 2 — Bağımsız ikinci rapor (başka bir AI)

Aynı problemi bağımsız değerlendiren ikinci rapor. **%80 örtüşme** — iki bağımsız analiz aynı çekirdek kararlara vardı, bu güçlü bir sinyal.

**Tur 2'nin Tur 1'e kattıkları:**

- Belge durumlarına `Bu dosya için gerekli değil` ve `Süresi doldu` eklenmesi
- PM'in dosyaya özel ek belge talebi açabilmesi
- KVKK derinliği: veri kaynağı, aydınlatma tarihi, kanal bazlı izin/ret alanları, "bir daha aranmasın"
- Yurt dışı aktarımda standart sözleşme → 5 iş günü içinde Kurum'a bildirim
- E-imza konusunda daha sert duruş (parola saklama / RPA kesinlikle hayır)
- Performans raporlarında "kaç arama yaptı" metriğinin manipüle edilebilirliği

**Tur 2'nin hataları (Tur 3'te düzeltildi):**

- Süre tahmini şişkin (tek geliştirici 5–8 ay)
- ASP.NET Core + React önerisi ekip yetkinliği sorulmadan verilmiş
- PostgreSQL RLS bu ölçek için erken
- 17 proje statüsü — belge seviyesindeki bilgiyi dosya statüsüne kopyalıyor
- 7 rol fazla
- Şablon kopyalanırken kaynak bağı korunmuyor
- İşletme maliyeti ve hakediş hiç ele alınmamış

### Tur 3 — Karşılıklı değerlendirme ve uzlaşı

Her iki taraf da eleştirileri değerlendirdi. **Kabul edilen düzeltmeler:**

| Konu | Eski | Yeni |
|---|---|---|
| Takvim | 7–9 hafta (hatalı hesap) | Pilot 5–6 hafta, üretim MVP 11–15 hafta |
| Statü süresi | `status_changed_at` tek alan | `status_history` tablosu + alan denormalize önbellek olarak |
| Lead modeli | `leads` içinde arama bilgisi | `leads` + ayrı `interactions` tablosu |
| Dosya deposu | MinIO aynı VPS'te | Ayrı makine veya replikasyon zorunlu |
| Media Library | "dosya+sürüm verir" | Revizyon zincirini **vermez**, kendiniz modellersiniz |
| İYS | Faz 3 | Entegrasyon Faz 2, **izin/ret alanları v1** |
| KVKK | "TR lokasyon çözer" | TR lokasyon + **alt işleyen envanteri** |
| e-TUYS | "API yok" | "Kamuya açık API bulunamadı, ilk kapsama taahhüt edilmez" |
| Maliyet | Tek tablo | Altyapı ≠ toplam sahip olma maliyeti |
| Webhook | Hepsi v1 | İç domain olayları v1, dış webhook Faz 3 |
| Hakediş | Çeşitlendirme fikri | Keşif sorusu (§11) |
| OCR | Faz 2 | Ölçüm sonrası Faz 2 adayı |

**Reddedilen tek öneri:** iş akışı sürümleme → §5.4'te gerekçesiyle.

---

## 3. Kesinleşen kararlar (ADR)

### K-01 · Platform: Web uygulaması, PWA olarak kurulabilir

**Karar:** Tarayıcıdan çalışan web uygulaması. Responsive + PWA (installable, ikon, push).

**Gerekçe:** Masaüstünün iki gerçek üstünlüğü çevrimdışı çalışma ve yerel donanım erişimi — ikisi de bu senaryoda yok. Buna karşılık iş tanımı gereği çok kullanıcılı ortak veri: pazarlama sahada, PM ofiste, yetkili ikisini birden görüyor. Masaüstü yapılsa da altına sunucu + DB gerekir, avantaj sıfırlanır.

**Reddedilen:** Klasik masaüstü uygulama (kurulum/güncelleme yükü, mobil için yeniden geliştirme, merkezi yetki karmaşası).

**Kaçış yolu:** Müşteri gerçekten paketlenmiş program isterse aynı web kodu **Tauri** ile sarılabilir. **Süre taahhüdü verilmez** — paketleme küçük bir iştir, ama kod imzalama (Apple notarization, Windows sertifikası), otomatik güncelleme kanalı, dosya protokolleri ve platform testleri ayrı efor kalemleridir. Talep gelirse ayrıca fiyatlanır.

Web kararı ertelenemez, masaüstü kararı ertelenebilir.

---

### K-02 · Ürün: Sıfırdan, dar kapsamlı geliştirme

**Karar:** Özel geliştirme. Hedef **~14 ekran, ~15 tablo**.

**Gerekçe:** Uygulamanın %70'i (firma kartı, kullanıcı, yetki, yorum, dosya eki, geçmiş) her CRM'de var. Ayırt edici %30 — programa göre değişen, sürümlenen, koşullu evrak şablonu motoru — hiçbir hazır üründe yok. Genel bir CRM'i buna zorlamak yazmaktan pahalı.

**Değerlendirilen alternatifler:**

| Seçenek | Puan | Not |
|---|---|---|
| Özel web uygulaması | 9/10 | **Seçildi** |
| Frappe Framework (ERPNext'siz) | 8/10 | Bütçe kısıtında **yedek plan** — DocType, workflow, izin, versiyon geçmişi, yorum, dosya eki hazır |
| ERPNext özelleştirme | 6/10 | Müşteri reddetti |
| Odoo | 6/10 | Lisans + yükseltme yükü |
| Twenty / EspoCRM | 5/10 | Genel CRM; evrak motoru yine yazılacak, çekirdek değişikliği yükseltme borcu |
| Hazır SaaS CRM | 4/10 | Dinamik program/evrak süreci için çok fazla ek geliştirme |
| Masaüstü | 3/10 | K-01 |

> **Not:** Müşterinin reddettiği ERPNext'in *iş modülleriydi*, altındaki Frappe motoru değil. Bütçe çok sıkışırsa Frappe ile 1–2 haftalık teknik prototip yapılabilir.

---

### K-03 · Teknoloji yığını: Ekibin bildiği dil

**Karar:** Yığın seçiminin tek doğru kriteri **ekibin bugünkü yetkinliği**. Teknik üstünlük değil, takvim riski belirleyici.

### ✅ KARAR VERİLDİ — 10.08.2026

| | |
|---|---|
| **Dil / Framework** | **PHP 8.4+ · Laravel 13.24 · Filament 5.7** |
| **Veritabanı** | PostgreSQL 17 |
| **Kuyruk / önbellek** | Redis |
| **Nesne depolama** | S3 uyumlu (geliştirmede MinIO container, üretimde bulut) |
| **Çalıştırma** | Docker Compose — geliştirmede ve üretimde aynı yapı |

Sürümler **10.08.2026'da Packagist'ten doğrulandı**, tahminle yazılmadı. Geliştirme makinesindeki yerel PHP (`php@7.1`, kırık `libaspell` bağlantısı) kullanılmayacak; **bütün PHP/Composer/Artisan komutları container içinde** çalışır. Bu, ofis makinesindeki kurulumla birebir aynı ortamı garanti eder.

**Değerlendirilen ve elenen:** Next.js (audit + izin katmanı elden yazılacaktı, ~1–2 hafta ek) · Spring Boot / .NET (hazır yönetim paneli yok, en çok kod) · Django (Laravel'e yakın ama ekran üretimi Filament kadar hızlı değil) · Frappe (Python ekibi olsa ana aday olurdu; K-02'de yedek plan olarak kalıyor).

**Laravel neden varsayılan öneri:** Bu bir *form + iş akışı + evrak + denetim* uygulaması. `spatie/laravel-activitylog`, `spatie/laravel-permission`, Horizon (kuyruk), Scheduler (hatırlatma) olgun paketler. **Uyarı:** `spatie/laravel-medialibrary` dosya eki ve koleksiyon verir, **belge revizyon zincirini vermez** — sürüm tablosu elle modellenir.

**Ortak bileşenler (yığından bağımsız):**

- **PostgreSQL** — JSONB, `tsvector` tam metin arama, PITR yedekleme
- **S3 uyumlu nesne depolama** — uygulama sunucusundan **ayrı** (bkz. K-06)
- **Redis** — kuyruk + oturum
- **Docker Compose** — tek VPS'te app + postgres + redis + reverse proxy
- **Sentry** — hata izleme
- **Ortamlar:** geliştirme / staging / canlı. Staging'e gerçek müşteri verisi kopyalanmaz, maskelenir.

**Değişmez kural:** İş mantığı arayüzde değil **servis/domain katmanında**. Filament seçilirse arayüzün REST üzerinden konuşması şart değil; ama mobil veya müşteri portalı geldiğinde aynı servisleri çağıran REST katmanı günler içinde çıkar. Mantık controller'a sızarsa haftalar sürer.

---

### K-04 · Mimari: Modüler monolit

**Karar:** Tek uygulama, kod içinde modül sınırları ayrık (CRM / proje / belge / kullanıcı / bildirim).

**Gerekçe:** Bu ölçekte mikroservis = gereksiz dağıtım karmaşıklığı, daha fazla sunucu maliyeti, veri tutarlılığı sorunları, daha yavaş geliştirme. Gerçek ihtiyaç doğarsa belirli modüller sonradan ayrılabilir.

---

### K-05 · Statüler kodda enum değil, veritabanı satırı

**Karar:** Statüler ve geçişler `statuses` / `transitions` tablolarında. Yeni statü eklemek deploy değil, ayar kaydı.

**Gerekçe:** Müşteri "bu statüler daha sonra netleştirilir" dedi. Enum seçilirse ilk yılda 5–6 gereksiz sürüm çıkışı olur.

---

### K-06 · Belge deposu uygulama sunucusundan ayrı

**Karar:** İki kabul edilebilir kurulum: (a) yönetilen nesne depolama, (b) MinIO aynı makinede **ancak** ikinci sağlayıcıya sürekli kova replikasyonu ile.

**Gerekçe:** Sistemin bütün değeri evraklarda. MinIO'yu app ve Postgres ile aynı diske koymak, tek arızada hem veritabanını hem tüm belgeleri kaybetmek demek.

---

### K-07 · Yetki uygulama katmanında, RLS ertelendi

**Karar:** Sunucu tarafı policy + `own/team/all` kapsam alanı + varsayılan reddet + otomatik yetki testleri. PostgreSQL Row-Level Security **v1'de yok**.

**Gerekçe:** Tek şirketli ~15 kullanıcılı sistemde RLS'in bedeli faydasını aşıyor: her istekte oturum değişkeni set etme, pgbouncer transaction pooling çakışması, migration/seed için bypass rolü, hata ayıklamanın zorlaşması.

**Yeniden değerlendirme koşulu:** müşteri portalı açılırsa · çok kiracılı SaaS'a dönerse · kesin izole edilmesi gereken organizasyonlar girerse.

---

### K-08 · e-TUYS entegrasyonu ilk kapsama taahhüt edilmez

**Karar:** Kamuya açık resmî API bulunamadı. İlk sürümde entegrasyon **yok**.

**Bunun yerine:** alan eşlemesi (CRM alanları kurum formlarıyla aynı sıra/terim) · tek tuş başvuru paketi PDF'i · başvuru no / gönderim tarihi / sonuç takibi · resmî erişim sağlanırsa ayrıca değerlendirilir.

**Kesinlikle yapılmayacak:** E-imza parolasını sistemde saklamak veya e-imza sahibi yerine tarayıcı otomasyonu (RPA). Güvenlik açısından savunulamaz, kurum ekranları değiştikçe sürdürülemez. **Bu madde müşteriyle pazarlık konusu değil.**

---

### K-09 · İş akışı revizyonlanır, dosya kilitlenmez

**Karar — üç parça:**

1. **Dosyalar bir iş akışı sürümüne kilitlenmez.** Açık dosyalar her zaman **yürürlükteki** akışı kullanır. Aynı anda üç paralel süreçle çalışan bir ekip istemiyoruz.
2. **Akış yapılandırması yine de değişmez biçimde revizyonlanır.** Her düzenleme yeni bir `workflow_revisions` kaydı doğurur: statü + geçiş kümesinin anlık görüntüsü, `yürürlük_tarihi`, değiştiren, gerekçe. `status_history` satırları hangi revizyon altında oluştuklarını taşır. Böylece "bu dosya Mart'ta hangi kurala göre ilerledi?" sorusu cevaplanır.
3. **Yetim kontrolü + kontrollü toplu geçiş.** Bir statü veya geçiş pasifleştirilirken sistem sayar: *"Şu anda 12 dosya 'Müşteri onayı bekleniyor' statüsünde ve bu değişiklikten sonra çıkış geçişi kalmıyor."* Yönetici, bu dosyalar için bir hedef statü seçip toplu geçiş çalıştırmadan değişikliği kaydedemez. Toplu geçiş tek bir denetim olayı olarak kaydedilir.

**Gerekçe ve düzeltme:** Önceki sürümde (v1.1) yalnızca "statü silinmez, pasifleştirilir" denmişti. Bu **eksikti** — asıl tehlike statüde değil **geçişte**: bir geçiş kaldırıldığında ya da guard'ı sıkılaştırıldığında, o statüde bekleyen dosyalar çıkışsız kalır ve ekip veriyi elle zorlamaya başlar. Yetim kontrolü bu deliği kapatır.

Program şablonu ile iş akışı arasındaki fark korunuyor: **program şablonu sözleşmesel anlık görüntüdür** (2026 çağrısının evrak listesi o dosyanın hukuki bağlamı, dosya ona bağlı kalır); **iş akışı iç süreçtir** (herkes en güncel akışta çalışır, ama geçmiş yeniden üretilebilir olur).

---

---

### K-11 · Arayüz: Filament paneli + özel tema, hibrit ekran stratejisi

**Karar:** Tek panel, tek oturum, tek tasarım sistemi. İçinde iki tür ekran:

| Ekran tipi | Nasıl yapılır | Hangileri |
|---|---|---|
| **Yapılandırma / yönetim** | Filament Resource (kutudan çıkan CRUD) | Program, program sürümü, evrak şablonu, statü/geçiş, kullanıcı, rol, ayarlar |
| **Operasyonel** (ekibin günde 50 kez açtığı) | Filament paneli **içinde** özel Livewire bileşeni | Dosya panosu, dosya detayı, evrak checklist, arama listesi, gösterge paneli |

**Gerekçe:** Saf Filament en hızlısı ama günlük kullanılan ekranlar jenerik CRUD gibi durur. Ayrı bir React ön yüzü ise ikinci bir oturum, ikinci bir tasarım sistemi ve haftalarca ek iş demek. Hibrit, tek çatı altında kalırken ekibin yaşadığı 5 ekrana özel tasarım imkânı verir.

**Tasarım referansı — ERPNext DEĞİL.** ERPNext'in arayüzü işlevsel ama yoğun ve tarihi eski; müşteri zaten onu reddetti, görsel dilini kopyalamak ters sinyal olur. Referans alınacaklar: **Linear**, **Attio**, **Twenty CRM** — yoğun ama nefes alan, ince kenarlıklı, tek vurgu renkli, güçlü tipografik hiyerarşi.

**Bağlayıcı tasarım kuralları** (WP-14'te token olarak kodlanır, sonraki tüm paketler bunlara uyar):

- **Tek vurgu rengi.** Durum renkleri (bekliyor / tamam / gecikmiş / iptal) vurgu renginden ayrıdır ve yalnızca durum bildirir.
- **Nötr renkler seçilir, miras alınmaz** — hafif mavi-gri eğilimli gri skala; saf `#808080` kullanılmaz.
- **Katman ayrımı önce kenarlıkla.** Durağan kart ve paneller 1px kenarlık + arka plan tonuyla ayrılır. Yalnız etkileşimli kartın üzerine gelindiğinde çok hafif yükselti; menü, modal ve çekmece gibi geçici katmanlarda tek bir kaplama gölgesi kullanılabilir. Koyu temadaki durağan kartlar gölgesizdir.
- **Yoğunluk operasyonel.** Form kontrolleri 40px, yoğun tablo satırları 38px ritmindedir; bir ekranda 25–30 dosya görünmeli, 8 değil.
- **Hareket anlam taşır.** Tek hareket eğrisi ve 120/170/220 ms süreleri kullanılır; geçişler yalnız odak, durum ve katman değişimini açıklar. Hareket azaltma tercihi tüm hareketleri etkisizleştirir.
- **Sayfa geçişi süreklidir.** Filament SPA gezinmesi tam sayfa parlamasını azaltır; tarayıcı desteği varsa aynı kısa hareket sözleşmesiyle görünüm geçişi uygulanır.
- **Durum formla da kodlanır**, sadece renkle değil — rozet, şerit, ikon. Renk körlüğü ve siyah-beyaz çıktı için.
- **Sayısal sütunlarda `tabular-nums`.**
- **Açık ve koyu tema**, ikisi de tasarlanır — biri diğerinin ters çevrilmişi değil.
- Bileşen üretmeden önce token dosyasına bakılır; hiçbir pakette çiğ hex kodu yazılmaz.

---

## 4. Kapsam

### 4.1 Kapsam içi (v1)

- Firma + yetkili kişi kartı (KVKK/İYS izin alanlarıyla)
- Fırsat (lead) kaydı ve ayrı görüşme (interaction) kayıtları
- Dosya = firma × destek programı sürümü
- Yapılandırılabilir statüler ve geçiş kuralları (izin / koşul / yan etki)
- PM ataması ve iş yükü görünümü
- Program bazlı dinamik evrak listesi + koşullu evraklar
- PM'in dosyaya özel ek belge talebi açabilmesi
- Evrak yükleme, 9 durumlu belge akışı, sürümleme, geçerlilik tarihi
- Aktivite geçmişi (kim, ne, ne zaman) + değiştirilemez denetim kaydı
- Dosya altında yorum akışı + @bahsetme + iç/dışa açık ayrımı
- Hatırlatma, görev, son tarih uyarısı
- E-posta + şifre girişi, 4 rol + `own/team/all` kapsam
- Gösterge paneli, 6 sabit rapor görünümü, Excel dışa aktarım
- İç domain olayları / outbox altyapısı

### 4.2 Kapsam dışı (bilinçli)

Muhasebe · fatura / e-fatura · stok · İK / bordro · üretim · satın alma · pazarlama otomasyonu / e-posta kampanyası · çok kiracılı (multi-tenant) mimari · çok dilli arayüz · karmaşık BI / küp raporlama · kurum sistemlerine otomatik başvuru gönderimi · mikroservis mimarisi · native mobil uygulama · müşteri portalı · gelişmiş OCR/AI

### 4.3 Kapsam filtresi — yalnızca iş özellikleri için

Önce ayrım: gelen her talep iki kutudan birine düşer.

**A · İş özelliği** (yeni ekran, yeni modül, yeni alan) → filtreye tabidir:

> "Bu, bir dosyanın hangi statüde olduğunu ya da hangi evrağın eksik olduğunu göstermeye yarıyor mu?"

Hayırsa Faz 3 kuyruğuna gider, ayrı fiyatlanır.

**B · Zorunlu kalite / güvenlik / mevzuat gereksinimi** (yedekleme, denetim kaydı, yetki kontrolü, şifreleme, KVKK-İYS alanları, hata izleme, geri dönüş tatbikatı) → **filtreye tabi değildir, kapsam içidir.**

> Bu ayrım olmadan kapsam filtresi kendi güvenlik önlemlerinizi de eler. "Yedekten geri dönüş tatbikatı dosya statüsünü göstermiyor" diye kesilirse, filtre projeyi korumaz, sakatlar.

---

## 5. Statü mimarisi

### 5.1 Üç bağımsız akış

Tek statüde birleştirilmez: **fırsat**, **dosya**, **belge**.

### 5.2 Fırsat hattı (pazarlama)

```
Yeni → Arandı → İlgileniyor → Teklif gönderildi → İş alındı
                                                 ↘ Kaybedildi (neden zorunlu)
                                                 ↘ Sonra aranacak (tarih + sorumlu zorunlu)
                                                 ↘ Aranmak istemiyor → aranmasın = true
```

**"İş alındı" seçildiğinde sistem otomatik olarak:**

1. Yeni dosya (deal) oluşturur
2. Hangi destek programı olduğunu sorar
3. O programın **geçerli sürümündeki** evrak listesini dosyaya kopyalar
4. Dosyayı "Atama bekliyor" statüsüne alır
5. Şirket yetkilisinin atama ekranına düşürür

### 5.3 Dosya hattı — **10 statüyü geçmez**

1. Atama bekliyor
2. PM atandı
3. Belgeler toplanıyor
4. Başvuru hazırlanıyor
5. Müşteri onayı bekleniyor
6. Kuruma gönderildi
7. Kurum değerlendirmesinde
8. Revizyon / ek belge bekleniyor
9. Sonuçlandı (onay / ret alt alanı)
10. Kapandı / iptal

**Ayrı statü OLMAYACAKLAR** — bunlar belge kayıtlarından hesaplanan türev bilgidir, dosya kartında rozet olarak gösterilir:

```
Belgeler toplanıyor · 5/8 geldi · 2 eksik · 1 incelemede · 1 süresi doldu
```

Aynı bilgiyi hem belge satırında hem dosya statüsünde tutmak çifte kayıttır: kullanıcı hangisini seçeceğini bilemez, iki yer birbirini tutmaz.

### 5.4 Belge durumları — 9 değer

`Talep edilecek` · `Talep edildi` · `Yüklendi` · `İnceleniyor` · `Kabul edildi` · `Eksik/hatalı` · `Yeni sürüm bekleniyor` · **`Bu dosya için gerekli değil`** · **`Süresi doldu`**

> Son iki değer atlanamaz. `Gerekli değil` olmazsa koşullu bir evrak N/A işaretlenemez, "evraklar tamam" geçişi hiç tetiklenmez, ekip statüyü elle zorlar — veri o noktada bozulur. `Süresi doldu` 30 günlük Findeks raporu gibi belgeler için gerekir.

### 5.5 Geçiş kuralları — üç kanca

| Kanca | Örnek |
|---|---|
| **İzin (guard)** | "Atama bekliyor → PM atandı" yalnızca `deal.assign` izni olan rolde |
| **Koşul (condition)** | "Belgeler toplanıyor → Başvuru hazırlanıyor" ancak zorunlu belgelerin hepsi `Kabul edildi` veya `Gerekli değil` ise. Aksi hâlde: "3 zorunlu evrak eksik: Findeks Raporu, YMM Bildirim Formu, Fizibilite Raporu" |
| **Yan etki (effect)** | Aktivite kaydı yazılır, `status_history` satırı kapanır/açılır, bildirim gider, gerekiyorsa otomatik görev açılır |

### 5.6 Süre ölçümü — iki katman

- **`status_history`** — gerçek kaynak. `entered_at` / `exited_at`. Dosya aynı statüye ikinci kez döndüğünde toplam süre ancak buradan çıkar.
- **`deals.status_changed_at`** — denormalize önbellek. Kanban panosu her açılışta 500 dosya için geçmiş tablosuna join atmasın diye.

**Ortalama belge toplama süresi statüden hesaplanmaz**, şu damgalardan hesaplanır: `document_requested_at`, `first_document_received_at`, `all_required_accepted_at`.

---

## 6. Evrak şablon motoru

> Sistemin gerçek değeri burada. Hiçbir hazır üründe yok.

### 6.1 Prensip

"Yeşil Sanayi Desteği açıldı, fizibilite raporu isteniyor" dendiğinde **kimse kod yazmaz**. Sistem yöneticisi ayarlardan yeni program sürümü açar, evrak satırlarını girer, o programa açılan her yeni dosyada checklist otomatik oluşur.

### 6.2 Program tanımında bulunacaklar

Program adı · kod · ilgili kurum · çağrı dönemi · açılış/kapanış tarihleri · açıklama · süreç adımları · zorunlu belgeler · koşullu belgeler · belge açıklamaları · kabul edilen dosya türleri · geçerlilik süresi gerekip gerekmediği · kontrol/onay sorumluları · aktif mi

### 6.3 Gerçek örnek — KOSGEB Yeşil Sanayi Destek Programı

| Evrak | Zorunlu | Koşul | Format | Geçerlilik |
|---|---|---|---|---|
| YMM / Bağımsız Denetçi Bildirim Formu | Evet | — | PDF | — |
| Bağlantı Anlaşmasına Çağrı Mektubu | Evet | — | PDF | — |
| Findeks Raporu | Evet | — | PDF | 30 gün |
| Dünya Bankası Çevresel ve Sosyal Durum Tespiti Formu | Evet | — | PDF/DOCX | — |
| Hasar Durumu Belgesi | Koşullu | `firma.il ∈ deprem bölgesi 11 il` | PDF | — |
| Fizibilite Raporu | Koşullu | büyük ölçekli / tutar eşiği | PDF + XLSX | — |
| Proje Başvuru Formu | Evet | — | KOBİ Bilgi Sistemi çıktısı | — |

**Bu tablo sistemde veridir, kod değil.**

### 6.4 Koşullu evrak ifadesi

Kural motoru yazılmaz. Basit JSONB koşulu %95 vakayı karşılar:

```json
{
  "all": [
    { "field": "company.city", "op": "in", "value": ["Adıyaman","Hatay","Kahramanmaraş","Malatya"] },
    { "field": "deal.amount",  "op": "gt", "value": 5000000 }
  ]
}
```

Dosya açılırken ve firma/dosya bilgisi değiştiğinde motor koşulları yeniden değerlendirir.

**Koşul sağlanır hale gelirse:** yeni `deal_documents` satırı eklenir, PM'e bildirim gider — *"Bu dosyaya 1 yeni zorunlu evrak eklendi: Hasar Durumu Belgesi."*

**Koşul artık sağlanmıyorsa — satır ASLA otomatik silinmez.** Altına dosya yüklenmiş olabilir; sessiz silme veri ve kanıt kaybıdır. Doğru davranış:

1. Sistem bir **öneri** üretir: *"Talep tutarı düştü — 'Fizibilite Raporu' artık zorunlu görünmüyor. 'Gerekli değil' olarak işaretlensin mi?"*
2. PM onaylarsa satır **pasifleşir** (`Bu dosya için gerekli değil`), silinmez; yüklenmiş dosyalar erişilebilir kalır.
3. Hem öneri hem karar denetim kaydına yazılır (kaynak: `otomasyon` / `kullanıcı`).
4. PM onaylamazsa satır zorunlu kalmaya devam eder — kurum yine isteyebilir, karar insanındır.

### 6.5 Kopyalama + referans — ikisi birden

Dosya açılırken şablon satırları `deal_documents`'a **kopyalanır** (ad, açıklama, zorunluluk, koşul anlık görüntü olarak). Ama sadece kopyalamak yetmez — kaynak bağı koparsa "bu belge hangi program kuralından geldi?" cevapsız kalır.

```
deal_documents
  - deal_id
  - source_doc_template_id      (nullable)
  - source_program_version_id
  - name_snapshot
  - description_snapshot
  - required_snapshot
  - condition_snapshot
  - status
```

`source_doc_template_id` **NULL** ise: PM'in yalnızca bu dosyaya özel açtığı ek belge talebidir. Kurum ek belge istediğinde şart.

### 6.6 Sürümleme kuralları

- Açık dosyalar açıldıkları **program sürümüne** bağlı kalır
- Yeni sürüm öncekinden **kopyalanarak** açılır (20 evrağı yeniden yazmak yerine 2'sini düzenlersiniz)
- `başvuru_kapanış` tarihine 30/15/7/1 gün kala ilgili dosyaların PM'leri uyarılır — teşvik işinde en pahalı hata kaçırılan son tarihtir

### 6.7 Bedava kazanç

Aynı şablon tablosu, PM'in müşteriye göndereceği **evrak talep e-postasını / PDF listesini** de üretir. "Eksik evrak listesini firmaya gönder" tek tuş — günlük hayatta en çok sevilecek özellik.

---

## 7. Yetkilendirme

### 7.1 Roller — 4 tane, kapsam ayrı alan

```
Rol:    Pazarlama | Proje Yöneticisi | Şirket Yetkilisi | Sistem Yöneticisi
Kapsam: own | team | all
```

Denetçi, finans, salt-okuma rolleri gerçek ihtiyaç doğduğunda eklenir. Rolü sonradan bölmek kolay, birleştirmek zor.

### 7.2 İzin matrisi

| İzin | Sistem Yön. | Şirket Yetkilisi | Proje Yöneticisi | Pazarlama |
|---|:--:|:--:|:--:|:--:|
| Firma oluştur / düzenle | ✓ | ✓ | ✓ | ✓ |
| Fırsat + görüşme kaydı | ✓ | ✓ | — | ✓ |
| Dosya oluştur | ✓ | ✓ | — | ✓ (iş alındıysa) |
| **PM ata / değiştir** | ✓ | ✓ | — | — |
| Statü ilerlet | ✓ | ✓ | ✓ (kendi) | — |
| Evrak yükle / onayla | ✓ | ✓ | ✓ (kendi) | — |
| Evrak indir | **hayır** (bkz. 7.2.1) | ✓ | ✓ (kendi) | — |
| Tutar / hakediş gör | **hayır** | ✓ | *açık soru* | — |
| Program & şablon düzenle | ✓ | ✓ | — | — |
| Kullanıcı yönet | ✓ | — | — | — |
| Denetim kaydını gör | ✓ | ✓ | kendi dosyası | kendi kaydı |
| **Görünürlük kapsamı** | Sistem ayarları | Tüm iş verisi | Atanan + ekip | Kendi açtıkları |

### 7.2.1 Sistem yöneticisi ≠ tüm müşteri verisine erişim

**Teknik yönetim ile iş verisi erişimi ayrı izinlerdir.** Sistem yöneticisi kullanıcı açar, rol tanımlar, program şablonu düzenler, ayarları değiştirir — ama **varsayılan olarak müşteri belgelerini okuyamaz**. Bu sistemde firmaların Findeks raporları, mali tabloları ve fizibiliteleri duruyor; en az yetki ilkesi gereği bir BT yöneticisinin bunları görmesi için ayrı bir sebep gerekir.

Uygulama:

- İzinler ikiye ayrılır: `system.*` (kullanıcı, rol, ayar, şablon) ve `deal.view_all` / `document.download` (iş verisi).
- Bir kişi her ikisine de sahip olabilir — ama bu **bilinçli bir atama** olur, rolün otomatik sonucu değil.
- **Break-glass:** acil durumda sistem yöneticisi geçici erişim alabilir; bu erişim gerekçe ister, süre sınırlıdır ve şirket yetkilisine bildirim gider. Her kullanım denetim kaydında ayrı bir olay tipidir.

### 7.3 Güvenlik temel çizgisi

- E-posta + parola, `bcrypt`/`argon2`, güçlü parola politikası
- **2FA (TOTP) isteğe bağlıdır:** varsayılan kapalıdır; kullanıcı kendi profilinden açar ve kapatır, açtığında sonraki girişlerde kod sorulur. Müşteri bu kullanım kolaylığı tercihini, Şirket Yetkilisi hesabının tüm iş verisini görmesi riskini bilerek kabul etti
- Ortak hesap yasak — herkes kendi hesabı
- HttpOnly + Secure + SameSite çerez, hareketsizlik zaman aşımı, "tüm oturumları kapat"
- Başarısız giriş sınırı, hız sınırlama, CSRF koruması, girdi şema doğrulaması
- **Yetki kontrolü sunucu tarafında, varsayılan reddet.** Arayüzde butonu gizlemek yetki değildir
- Yetki kurallarının otomatik testi — el ile doğrulanamaz
- Yetki değişiklikleri loglanır; proje ataması kaldırılınca erişim otomatik kapanır
- Hassas veri dışa aktarma yetkisi ayrıca tanımlanır
- Google Workspace / M365 varsa SSO eklenir

---

## 8. Aktivite geçmişi, denetim ve yorumlar

### 8.1 İki katman

**Katman 1 — Aktivite akışı (uygulama).** Kullanıcıya gösterilen. Kayıtta ham JSON değil **olay tipi + parametreler** durur, ekranda Türkçe cümleye çevrilir. Dosya detayında tek zaman tüneli: yorumlar ve olaylar iç içe. Filtre: yalnız statü / yalnız evrak / yalnız yorum.

**Katman 2 — Denetim kaydı (veritabanı).** Kritik tablolarda `AFTER INSERT/UPDATE/DELETE` tetikleyicisi → tek `audit_log` tablosu, JSONB delta. Hangi yoldan gelirse gelsin (uygulama, toplu iş, elle SQL) yakalar. Aylık partition. **Uygulama rolüne `UPDATE`/`DELETE` izni verilmez — salt ekleme.**

### 8.2 Tetikleyici uygulama kullanıcısını kendiliğinden BİLEMEZ

Bu, migration yazmadan önce çözülmesi gereken bir uygulama detayı. Postgres tetikleyicisi yalnızca `current_user`'ı (veritabanı rolü) görür — uygulamadaki `Mehmet Kaya`'yı, oturumu, IP'yi görmez. Çözüm:

```sql
-- Her transaction'ın başında uygulama katmanı yazar:
SET LOCAL app.actor_id   = '42';
SET LOCAL app.session_id = 'sess_...';
SET LOCAL app.client_ip  = '10.0.0.5';
SET LOCAL app.source     = 'user';   -- user | automation | integration

-- Tetikleyici okur:
current_setting('app.actor_id', true)   -- yoksa NULL döner, hata vermez
```

Kurallar:

- `SET LOCAL` kullanılır — transaction bitince temizlenir, connection pool'da sızmaz. Bunu bir middleware/interceptor otomatik yapar, geliştiricinin hatırlamasına bırakılmaz.
- Değer yoksa (doğrudan `psql`, elle SQL, migration) kayıt **`system` / `unknown`** olarak yazılır. Bu bir hata değil, kasıtlı davranış: kaynağı bilinmeyen değişikliğin *bilinmediğinin* kaydı tutulur.
- Arka plan işleri `automation`, entegrasyonlar `integration` kaynağıyla yazar.

**Denetim JSON'undan hariç tutulacak alanlar (beyaz liste değil, kara liste yetmez — açıkça hariç tutun):** parola hash'i, oturum ve API token'ları, 2FA gizli anahtarı, imzalı URL secret'ları, e-imza ile ilgili her şey. Bu alanlar `audit_log`'a düşerse denetim kaydı kendisi bir sızıntı yüzeyi olur.

### 8.3 Denetim kaydında bulunacaklar

İşlemi yapan kullanıcı · tarih-saat · işlem türü · eski değer / yeni değer (hassas alanlar hariç) · ilgili kayıt · oturum + IP · varsa dosya sürümü · **işlemin kaynağı** (kullanıcı / otomasyon / entegrasyon / system-unknown)

### 8.4 Ekranda görünen hali

```
10.08.2026 14:22  Ayşe Demir   dosyayı Mehmet Kaya'ya atadı
10.08.2026 14:22  Ayşe Demir   statüyü "Atama bekliyor" → "PM atandı" yaptı
11.08.2026 09:05  Mehmet Kaya  evrak talebini firmaya gönderdi (7 evrak)
13.08.2026 16:40  Mehmet Kaya  "Findeks Raporu" belgesini yükledi (findeks_2026.pdf, 1.2 MB)
13.08.2026 16:41  Mehmet Kaya  "Findeks Raporu" durumunu Talep edildi → Kabul edildi yaptı
14.08.2026 10:12  Sistem       "Fizibilite Raporu" zorunlu hale geldi (talep tutarı güncellendi)
14.08.2026 11:30  Mehmet Kaya  yorum: "Firma fizibiliteyi 20 Ağustos'a yetiştireceğini söyledi."
```

**Tasarım kuralı:** Kullanıcıya asla `{"status_id": {"old": 3, "new": 4}}` gösterilmez. Alan adı → Türkçe etiket ve id → etiket eşlemesi olay yazılırken **anlık olarak** kaydedilir; yoksa 2 yıl sonra silinmiş bir statünün geçmişi okunamaz.

### 8.5 Yorumlar

- Her dosyanın altında; ayrıca fırsat ve belge satırı seviyesinde
- **@bahsetme** → bildirim
- Yorumların tamamı yalnız sistem kullanıcılarına açık **iç not** niteliğindedir; müşteri görünürlüğü ve müşteri portalı yoktur
- Düzenlenebilir ama "düzenlendi" işareti + önceki sürüm denetim kaydında kalır
- Yorumlar denetim kayıtlarından **ayrı** tutulur

---

## 9. Belge saklama

| Konu | Karar |
|---|---|
| Nerede | S3 uyumlu nesne deposu, **uygulama sunucusundan ayrı** (K-06) |
| Anahtar şeması | **Opaque UUID** — `files/{uuid}` veya `files/{uuid[0:2]}/{uuid}`. `deal_id` ve orijinal dosya adı anahtara **konmaz**: nesne deposu erişim logları, CDN kayıtları ve hata izlerinde firma/dosya bilgisi sızar. Orijinal ad ve ilişkiler veritabanında durur, indirmede `Content-Disposition` ile geri verilir |
| Erişim | Herkese açık URL **yok**. Kısa ömürlü imzalı bağlantı (5–15 dk), üretimi yetki kontrolünden geçer |
| **İndirme kaydı** | **İmzalı bağlantı üretmek indirme değildir** — kullanıcı bağlantıyı hiç açmayabilir. İki ayrı olay tutulur: `belge.erişim_talebi` (bağlantı üretildi) ve `belge.indirildi`. İkincisi ancak dosya **uygulama üzerinden proxy edilirse** veya nesne deposunun erişim logları toplanırsa doğru yazılabilir. v1'de proxy endpoint önerilir (dosyalar küçük, ~2 MB); aksi hâlde yalnızca erişim talebi kaydedilir ve rapor bunu böyle etiketler |
| Sürüm | Yeni yükleme eskisini silmez, `sürüm_no` artar. Kullanıcı `fizibilite-son-final2.pdf` değil **"Fizibilite Raporu — Sürüm 3 · Kabul edildi"** görür |
| S3 tarafı | Versioning açık; kritik belgelere gerekirse Object Lock |
| Doğrulama | MIME + uzantı + boyut kontrolü, `sha256` mükerrer tespiti, arka planda ClamAV. Beyaz liste: PDF, DOCX, XLSX, JPG, PNG |
| Geçerlilik | Süresi dolan belge otomatik `Süresi doldu` olur, dosya "evraklar tamam" koşulundan düşer |
| Ölçek | Dosya başına ~15 evrak × ~2 MB ≈ 30 MB. Yılda 500 dosya ≈ 15 GB |

**Uyarı:** Hazır dosya-eki paketleri (ör. Laravel Media Library) revizyon zincirini kutudan vermez — sürüm tablosu elle modellenir.

---

## 10. Veri modeli

```
companies ──< contacts ──< communication_consents      (append-only izin defteri)
    │
    ├──< leads ──> primary_contact
    │       ├──< interactions ──> contact
    │       └──> (dönüşüm) deals
    │
    └──< deals ──< deal_documents ──< files
           ├──< interactions
           ├──< status_history
           └──< (polymorphic subject) comments · tasks · activities

programs ──< program_versions ──< doc_templates
                    └──> deals

statuses ──< transitions          workflow_revisions (değişmez akış anlık görüntüsü)
teams ──< team_members
users >──< roles >──< permissions ──< role_permission_history
notifications · outbox · audit_log

companies / leads / deals / deal_documents ──< comments · tasks · activities
```

### 10.0 Polymorphic subject kuralı

`comments`, `tasks` ve `activities` dört farklı nesneye bağlanabilmeli: **company**, **lead**, **deal**, **deal_document**. Firma geneli notlar şirket öznesinde; programa veya projeye ait notlar kendi öznesinde kalır.

Kullanılacak desen — **kontrollü polymorphic**, serbest string değil:

```
subject_type  ENUM('company','lead','deal','deal_document')   -- CHECK ile sınırlı
subject_id    BIGINT
INDEX (subject_type, subject_id)
```

Alternatif (daha katı, daha çok kolon): her hedef için ayrı nullable FK + `CHECK` ile "tam olarak biri dolu". Veri bütünlüğü gerçek FK ile korunacaksa bu tercih edilir. **Faz 0'da karara bağlanacak** — ikisi de savunulabilir, ama şema yazılmadan seçilmeli.

### 10.1 Tablolar

| Tablo | Kritik alanlar | Not |
|---|---|---|
| `companies` | unvan, **vergi_no (tekil)**, vergi_dairesi, nace_kodu, il/ilçe, ölçek, personel_sayısı, kaynak | Tekil indeks mükerrer firmayı kökten engeller |
| `contacts` | ad, unvan, telefon, e-posta, birincil_mi + **güncel izin özeti** (izin_arama, izin_sms, izin_eposta, `aranmasın`) | Unvan kişinin şirket içindeki görevini anlatır. İzin alanları yalnızca hızlı sorgu için denormalize özet; gerçek kaynak `communication_consents` |
| `communication_consents` | contact_id, **kanal** (arama/sms/eposta), **amaç** (pazarlama/hizmet), **durum** (onay/ret), hukuki_sebep, kaynak (form/telefon/liste/referans/İYS), aydınlatma_tarihi + yöntemi, kanıt (JSONB/dosya ref), İYS_referansı, geçerlilik_başlangıç, kayıt_zamanı, kaydeden | **Append-only.** Satır güncellenmez, yeni satır eklenir; güncel durum en son satırdır. Mutable kolonla tutulursa **önceki onayın kaynağı, hukuki sebebi ve kanıtı kaybolur** — KVKK/İYS savunmasının tamamı bu kanıta dayanıyor |
| `leads` | company_id, **primary_contact_id**, sahip_user_id, kaynak, ilgilenilen_program, statü, tekrar_arama_tarihi, kayıp_nedeni | **Programa özel fırsatın kendisi**, tek bir aramanın veya firmanın statüsü değil |
| `interactions` | lead_id / deal_id, **contact_id**, user_id, tip (telefon/toplantı/e-posta), tarih, süre, sonuç, not | Bir firma beş kez aranır; her satırda gerçekten görüşülen kişi bellidir |
| `programs` | ad, kurum, kod, aktif_mi | KOSGEB / TÜBİTAK / Sanayi Bak. / Kalkınma Ajansı |
| `program_versions` | program_id, çağrı_dönemi, başvuru_açılış, başvuru_kapanış, aktif_mi | Sürümleme şart |
| `doc_templates` | program_version_id, ad, açıklama, zorunlu_mu, koşul (JSONB), kabul_edilen_format, geçerlilik_süresi_gün, sıra | Motorun kalbi |
| `deals` | company_id, program_version_id, status_id, **status_changed_at**, pm_user_id, açan_user_id, talep_tutarı, başvuru_no, başvuru_tarihi, karar_tarihi, öncelik, **document_requested_at, first_document_received_at, all_required_accepted_at** | Merkez nesne |
| `status_history` | deal_id, status_id, **status_label_snapshot**, entered_at, exited_at, changed_by, transition_id, gerekçe | Zorunlu — §5.6 |
| `deal_documents` | deal_id, **source_doc_template_id (nullable)**, source_program_version_id, ad/açıklama/zorunluluk/koşul anlık görüntüleri, durum, teslim_tarihi, son_tarih, geçerlilik_bitiş, notlar | §6.5 |
| `files` | **deal_document_id (FK, zorunlu)**, storage_key (opaque UUID), orijinal_ad, mime, boyut, **sha256**, sürüm_no, yükleyen, tarama_sonucu, silindi_mi | v1.1'de FK eksikti — dosya hangi belge gereksinimine ait olduğunu bilmiyordu. `(deal_document_id, sürüm_no)` tekil |
| `activities` | actor_id, **subject_type + subject_id** (company/lead/deal/deal_document), action, changes (JSONB), **kaynak** (kullanıcı/otomasyon/entegrasyon/system-unknown), ip, user_agent, created_at | Asla güncellenmez/silinmez |
| `comments` | **subject_type + subject_id** (company/lead/deal/deal_document), user_id, gövde, mentions (JSONB), **görünürlük (iç/dışa açık)**, parent_id, düzenlendi_mi | Firma notuyla belirli işin notu birbirine karışmaz |
| `tasks` | **subject_type + subject_id** (company/lead/deal/deal_document), atanan, başlık, son_tarih, tamamlandı_mı, hatırlatma_zamanı | "3 gün sonra tekrar ara" bir **lead** görevidir; firma geneli görev ayrıca mümkündür |
| `teams` / `team_members` | ad, yönetici_user_id / team_id, user_id, rol | `own/team/all` kapsamındaki **team** bunlar olmadan tanımsızdı |
| `notifications` | user_id, tip, subject_type + subject_id, gövde, okundu_mu, kanal, gönderim_durumu | Uygulama içi zil + e-posta kuyruğu |
| `outbox` | olay_tipi, payload (JSONB), oluşturulma, işlenme_zamanı, deneme_sayısı, hata | §13.1 — v1'de kurulur |
| `workflow_revisions` | anlık_görüntü (JSONB: statüler + geçişler), yürürlük_tarihi, değiştiren, gerekçe | K-09 |
| `statuses` | kod, etiket, tip (fırsat/dosya), renk, sıra, bitiş_mi, **is_active** | Silinmez, pasifleştirilir |
| `transitions` | from_id, to_id, gereken_izin, koşul, **is_active** | Geçiş de silinmez — K-09 yetim kontrolü |
| `users` / `roles` / `permissions` | + rol başına `kapsam` (own/team/all) | |
| `role_permission_history` | rol/izin/kullanıcı, eski, yeni, değiştiren, zaman | Yetki değişikliği loglanacaktı (§7.3) — tablosu yoktu |
| `audit_log` | tetikleyici tabanlı, JSONB delta, aylık partition, **actor session context'ten** | Katman 2 — §8.2 |

---

## 11. Ekranlar

| # | Ekran | İçerik |
|---|---|---|
| 1 | **Ana panel** (role göre) | Bugün aranacaklar · geciken takipler · bana atanan yeni işler · belgesi eksik dosyalar · son tarihi yaklaşan başvurular · PM atanmamış işler · son aktiviteler |
| 2 | **Pazarlama — arama listesi** | Yoğun, satır tabanlı çalışma alanı. Tıkla ara, sonucu iki dokunuşta gir |
| 3 | **Takip panosu** | Statü sütunları arasında sürükle-bırak, karttan hızlı detay |
| 4 | **Firma 360°** | Firma bilgileri · yetkililer · iletişim geçmişi · açık/geçmiş dosyalar · belgeler · yorumlar · tüm aktivite |
| 5 | **Dosya detayı** | Sekmeler: Genel · Süreç · Belge listesi · Görevler · Yorumlar · Görüşmeler · Ekip · İşlem geçmişi |
| 6 | **Dosya panosu** | Kanban, statü sütunları, rozetler |
| 7 | **Atama ekranı** | Bekleyen dosyalar + PM iş yükü yan yana |
| 8 | **Program yönetimi** | Program · sürüm · evrak şablonu · koşullu kurallar · pasife alma |
| 9 | **Belge inceleme** | Yüklenen sürümler, kabul/ret + gerekçe |
| 10 | **Görevler / takvim** | Son tarihler, hatırlatmalar |
| 11 | **Raporlar** | §11.1 |
| 12 | **Kullanıcı & rol yönetimi** | |
| 13 | **Ayarlar — statü ve geçişler** | |
| 14 | **Bildirim merkezi** | |

### 11.1 Raporlar — 6 sabit görünüm + Excel

Dosya panosu · bekleyen atamalar · PM iş yükü · eksik evrak listesi · yaklaşan son tarihler · dönüşüm hunisi (program bazında başarı oranı).

**Ölçülecek iki kritik metrik:** statüde geçen süre (nerede tıkanıyoruz) ve evrak toplama süresi (ilk talepten tamamlanmaya kaç gün).

> Performans raporu yalnızca "kaç arama yaptı" gibi manipüle edilebilir sayıya dayanmamalı; sonuç ve iş kalitesi de görünmeli.

---

## 12. Bildirimler ve KVKK/İYS

### 12.1 Kanallar

| Kanal | Ne zaman | Faz |
|---|---|---|
| Uygulama içi | Atama, bahsetme, statü değişikliği, evrak onayı | 1 |
| E-posta | Atama, son tarih (30/15/7/1 gün), günlük özet | 1 |
| Web push (PWA) | Acil atama, aynı gün son tarih | 2 |
| WhatsApp / SMS | Müşteriye evrak hatırlatma | 3 |

### 12.2 KVKK

- **Lokasyon: Türkiye — ama tek başına yetmez.** Alt işleyenleri ayrı ayrı inceleyin: e-posta sağlayıcısı, hata izleme, yedek hedefi, SSO, mesajlaşma. Her biri ayrı aktarım noktası, çoğu varsayılan olarak yurt dışı. "Sunucumuz Türkiye'de" cümlesi bu envanter çıkarılmadan uyum anlamına gelmez.
- Yurt dışı aktarım yapılacaksa uygun güvence şart; **standart sözleşme kullanılırsa imzadan sonra 5 iş günü içinde Kurum'a bildirim** yükümlülüğü var.
- VERBİS kaydı, aydınlatma metni, veri envanteri (hangi veri, hangi amaç, nerede, kimlerle, ne kadar süre)
- Saklama/imha politikası: her tablo için saklama süresi + süresi dolan kayıtlar için otomatik anonimleştirme
- Şifreleme: aktarımda TLS 1.3, diskte disk düzeyi, nesne deposunda sunucu tarafı. Yedekler de şifreli.

### 12.3 İYS ve pazarlama aramaları

Ticari nitelikli SMS/e-posta ve sesli aramalar için İYS onay/ret süreçleri işler. Tacir/esnaf alıcılar için istisnalar olabilse de **ret hakkı her hâlükârda geçerlidir**.

> **Zamanlama:** İYS *teknik entegrasyonu* Faz 2'ye kalabilir, ama **izin/ret kaydı v1'de zorunlu**.

**İzin defteri append-only olmalı.** İzinleri `contacts` üzerinde güncellenen kolonlar olarak tutmak, bir denetimde işe yaramaz: "bu numarayı Mart'ta hangi hukuki sebeple aradınız?" sorusunun cevabı, Nisan'da üzerine yazılmış bir kolonda yoktur. `communication_consents` tablosu her onay ve reddi ayrı satır olarak saklar — kanal, amaç, hukuki sebep, kaynak (form / telefon / liste / referans / İYS), aydınlatma tarihi, kanıt, İYS referansı, zaman ve kaydeden. `contacts` yalnızca güncel özeti taşır.

KVKK Kurumu, üçüncü kişilerden (liste, referans, tavsiye) elde edilen iletişim bilgilerinin aydınlatma ve işleme şartları sağlanmadan pazarlamada kullanılmasına dair açık uyarı yayımladı — soğuk arama yapan ekip için doğrudan risk. **Canlıya çıkmadan şirketin KVKK danışmanı pazarlama senaryolarını doğrulamalı.**

---

## 13. Entegrasyon yol haritası

| # | Entegrasyon | Kazanç | Zorluk | Faz |
|---|---|---|---|---|
| 1 | E-posta gönderimi (SMTP/SES) | Evrak talebi, hatırlatma, atama | Düşük | 1 |
| 2 | Dosyaya e-posta iliştirme (`dosya-1234@sirket.com`) | Firmayla yazışma otomatik dosyaya düşer | Orta | 2 |
| 3 | Takvim (Google/Outlook ICS) | Son tarihler PM takviminde | Düşük | 2 |
| 4 | M365 / Google Workspace + SSO | Parola yönetimi biter | Orta | 2 |
| 5 | İYS entegratörü | İzin/ret senkronu | Orta | 2 |
| 6 | WhatsApp Business API | TR'de en yüksek dönüş oranı | Orta | 3 |
| 7 | Belge OCR + LLM sınıflandırma | Gelen PDF otomatik doğru satıra | Orta | 3* |
| 8 | E-imza (KamuSM / özel SM) | Sözleşme, vekaletname | Yüksek | 3 |
| 9 | Santral / CTI | Arama kaydı otomatik fırsata | Yüksek | 3+ |
| 10 | Muhasebe (Logo/Netsis/Paraşüt) | Fatura, tahsilat | Orta | 3+ |
| 11 | e-TUYS / KOSGEB | Resmî erişim sağlanırsa | — | Belirsiz |

\* OCR'ın fazı **ölçümle** belirlenir — §14 açık sorular.

### 13.1 Olay altyapısı — ikiye ayrılır

**İç domain olayları + outbox → v1'de kurulur.** `lead.converted`, `deal.assigned`, `deal.status_changed`, `document.uploaded`, `document.rejected`. Bildirimler, aktivite kaydı ve otomatik görevler zaten bunlara abone olur; sonradan eklemek her çağrı noktasını yeniden açmak demektir.

**Dışa açık webhook → Faz 3.** Tek bir dış tüketici yokken yönetim paneli, yeniden deneme, imzalama, secret rotasyonu ve teslimat logu yazmak MVP'ye yük. İç altyapı hazırsa dış webhook sonradan birkaç güne çıkar.

---

## 14. Mobil

| Faz | Ne |
|---|---|
| 1–2 | **PWA.** Responsive + `manifest.json` + service worker. Ana ekrana eklenir, ikon, push (iOS'ta ana ekrana ekli olması şartıyla). Ek maliyet ~2–3 gün |
| — | **Çevrimdışı veri düzenleme v1'de yok.** Müşteri ve belge verisinin cihazda tutulması güvenlik + senkronizasyon riski. Sınırlı okuma önbelleği yeterli |
| 3 | **Native (React Native / Expo)** yalnızca şu ihtiyaç netleşirse: **kamerayla belge tarama**. Firma yetkilisinin masasındaki belgeyi telefonla çekip PDF'e çevirip checklist satırına yüklemek, PM'in en çok vakit kaybettiği işi bitirir |

Pazarlama için mobil öncelikli tek ekran: **"bugün aranacaklar"**. Sahada dizüstü açmayan ekip için sistemin benimsenmesi buna bağlı.

---

## 15. Yol haritası

> **Pilot ≠ üretim MVP'si.** İki tarihi müşteriye ayrı ayrı söyleyin. "5 haftada biter" denip 14. haftada teslim edilen proje, zamanında bitmiş projeden daha kötü karşılanır.

### Faz 0 — Süreç netleştirme · 1–2 hafta · 4–8 adam-gün

- Şirket yetkilisi + 1 PM + 1 pazarlamacı ile atölye
- Statü listelerinin ilk hâli, kim hangi geçişi yapabilir
- İlk 3–5 destek programı ve **gerçek** evrak listeleri
- Mevcut Excel/veri var mı — göç planı
- Tıklanabilir ekran prototipi; satış/proje/yönetici rollerinden en az birer gerçek kullanıcı test eder
- **Çıktı:** 8–10 sayfalık, kabul kriterli, **imzalı** kapsam belgesi

### Faz 1 — Çekirdek → dar pilot · 4–5 hafta · 20–25 adam-gün

- Giriş, 4 rol, `own/team/all` kapsamı, kullanıcı yönetimi
- Firma + yetkili (KVKK/İYS izin alanlarıyla) + fırsat + görüşme kaydı
- Dosya, statü makinesi (DB tabanlı) + `status_history`, PM atama
- Program & evrak şablonu yöneticisi + otomatik checklist üretimi
- Evrak yükleme, 9 durumlu belge akışı, sürümleme, imzalı indirme
- Aktivite geçmişi + yorum akışı + @bahsetme
- İç domain olayları / outbox altyapısı
- **5–6. hafta: tek PM + tek program ile dar pilot canlıya**

### Faz 2 — Üretim MVP'si · 5–8 hafta · 25–35 adam-gün

- Son tarih / hatırlatma motoru, görevler, günlük özet e-postası
- Koşullu evrak kuralları, geçerlilik süresi takibi
- Gösterge paneli, dönüşüm hunisi, PM iş yükü, süreç damgalı raporlar
- Global arama (firma, vergi no, başvuru no, evrak adı)
- PWA kurulumu + web push, mobil düzen
- Tek tuş "eksik evrak talebi gönder"
- Veri taşıma, kullanıcı eğitimi, kabul testi, canlıya alma
- **11–15. hafta: tam ekip üretim sürümüne geçer**

### Faz 3 — Genişleme · talebe göre · 25–60 adam-gün

Müşteri portalı · native mobil + kamera tarama · WhatsApp · e-posta iliştirme · takvim · sözleşme/teklif üretimi · e-imza · hakediş & tahsilat · OCR

### 15.1 Efor özeti

| Faz | Adam-gün | Takvim |
|---|--:|--:|
| Faz 0 | 4–8 | 1–2 hafta |
| Faz 1 | 20–25 | 4–5 hafta |
| Faz 2 | 25–35 | 5–8 hafta |
| **Geliştirme alt toplamı** | **49–68** | |
| Proje yönetimi + risk tamponu (~%12) | 6–7 | |
| **v1 toplam** | **55–75** | **11–15 hafta** |
| Faz 3 | 25–60 | — |

> **Düzeltme (v1.2):** v1.1'de "55–75" doğrudan toplam gibi yazılmıştı; faz aralıklarının toplamı **49–68**. Aradaki fark proje yönetimi ve risk tamponudur ve artık ayrı satırda gösteriliyor. Ticari teklife bu iki satır **ayrı ayrı** aktarılmalı — tampon gizlenirse ilk gecikmede güven kaybı olur.

Adam-gün ÷ 5 = ham geliştirme haftası; takvim buna müşteri beklemelerini, hata düzeltmeyi, veri taşımayı ve canlıya alma işini ekler. **İki geliştirici + yarı zamanlı test desteğiyle 7–10 hafta.** Frappe seçilirse Faz 1 ~%30 kısalır.

**Bu sürelerin geçerlilik şartı:** müşteri portalı, hakediş/tahsilat, OCR, e-posta iliştirme ve gerçek İYS entegrasyonu **v1 dışında kalır**. Bunlardan biri v1'e alınırsa takvim yeniden hesaplanır.

---

## 16. Barındırma ve maliyet

### 16.0 · K-10 — Ofisteki 7/24 makine sunucu olarak kullanılabilir (şartlı)

**Karar:** Ofiste zaten sürekli açık duran bilgisayar sunucu olarak kullanılır. **Pilot için koşulsuz uygun. Üretim için altı şart yerine geldiğinde uygun.**

**Neden mantıklı:** Donanım maliyeti sıfır, veri fiziksel olarak şirketin kendi tesisinde (KVKK açısından en temiz konum — yurt dışı aktarım sorunu hiç doğmaz), tam kontrol, aylık VPS gideri yok.

**Üretim için şartlar — ilk ikisi pazarlık dışı:**

| # | Şart | Neden |
|---|---|---|
| 1 | **UPS (kesintisiz güç)** | Elektrik kesintisinde PostgreSQL yazma sırasında kapanırsa veritabanı bozulabilir. UPS + düzgün kapanma betiği. Bu olmadan üretim yok |
| 2 | **Ofis dışına şifreli yedek** | Yangın, su baskını, hırsızlık: aynı odadaki her şey aynı anda gider. Günlük şifreli off-site yedek + WAL arşivi. Aylık birkaç dolar, pazarlık dışı |
| 3 | **Modemde port açma YOK** | Port yönlendirme, ofis ağının tamamını internete açar. Bunun yerine **Cloudflare Tunnel** veya **Tailscale** gibi *giden* bağlantı kuran çözüm: dışarıdan erişilebilir olur, ama makinede açık port bulunmaz. Statik IP sorununu da çözer |
| 4 | **Adanmış makine** | Kimsenin günlük kullandığı bilgisayar olmayacak. Windows/macOS otomatik güncelleme yeniden başlatması kapalı ya da kontrollü; birisi "kapatayım" diyemeyecek |
| 5 | **İki disk (mirror) veya çok sık yedek** | Tek SSD ölürse veri gider. RAID-1 ya da saatlik off-site |
| 6 | **Fiziksel güvenlik** | Kilitli oda / dolap, erişim kontrolü. Firmaların mali tabloları duruyor — KVKK'da fiziksel güvenlik de teknik tedbirin parçası |

**İnternet kesintisi kabul edilmiş risktir:** ofis hattı düştüğünde sisteme *hiç kimse* erişemez (evden çalışan dahil). Kritikse yedek hat / mobil modem failover düşünülür; değilse bu risk yazılı olarak kabul edilir.

**Hibrit kurulum önerilir:** uygulama + veritabanı ofiste, **nesne depolama ve yedek bulutta**. Bu, K-06'daki "belge deposu uygulama sunucusuyla aynı diskte olmasın" şartını da karşılar ve maliyeti aylık birkaç dolarda tutar.

**Geçiş yolu açık kalsın:** Docker Compose ile kurulduğu için, ileride kullanıcı sayısı artar ya da kesintiler sorun olursa **aynı yapılandırma VPS'e olduğu gibi taşınır**. Bu karar geri döndürülebilir; bu yüzden bugün ofis makinesiyle başlamak düşük risklidir.

> **Açık soru:** Makine *müşterinin* ofisinde mi, *sizin* ofisinizde mi? Sizin ofisinizdeyse müşterinin kişisel verilerini siz işliyorsunuz demektir — **veri işleyen sözleşmesi** gerekir ve KVKK sorumluluğu paylaşılır. Müşterinin ofisindeyse bu sorun yok, ama bakım/erişim yönteminin (uzak erişim, kim ne zaman girer) yazılı olması gerekir.

### 16.1 Altyapı (aylık, ~15 kullanıcı / ~500 aktif dosya)

> **Bu rakamlar varsayımsaldır.** Sağlayıcı teklifi alınana kadar bağlayıcı değil; **KDV ve yönetilen hizmet bedeli hariçtir.** Ticari teklifte "tahmini altyapı bandı" olarak, teklif alındıktan sonra kesinleşecek şekilde sunulmalı.

| Kalem | Ofis makinesi (K-10) | VPS |
|---|--:|--:|
| Sunucu | **0** *(+ elektrik: 24/7 ~60 W ≈ 43 kWh/ay)* | 20–45 |
| UPS | tek seferlik donanım | — |
| Tünel (Cloudflare Tunnel / Tailscale) | 0 *(ücretsiz katman yeter)* | — |
| Nesne depolama + replikasyon | 5–15 | 5–15 |
| Yedek depolama (ofis dışı, şifreli) | 3–10 | 3–10 |
| E-posta gönderimi | 0–15 | 0–15 |
| Alan adı + SSL | ~1 | ~1 |
| Hata izleme | 0–26 | 0–26 |
| **Altyapı toplamı (USD/ay)** | **~10–40 + elektrik** | **~30–85** |

Kişi başı lisans yok — kullanıcı eklemek bedava. *(Karşılaştırma: kişi başı ücretli SaaS CRM'de 15 kullanıcı ≈ 300–750 USD/ay, evrak motoru yine yok.)*

### 16.2 Sahip olma maliyetinin geri kalanı

| Kalem | Sıklık |
|---|---|
| Bakım, güvenlik yamaları, bağımlılık güncellemeleri | Aylık |
| **Ofis makinesi kullanılıyorsa:** donanım izleme (disk sağlığı, sıcaklık, UPS durumu) | Aylık |
| Kullanıcı desteği ve küçük değişiklikler | Aylık (ilk 3 ay yoğun) |
| Veri taşıma | Tek seferlik |
| Yedekten geri dönüş tatbikatı | Aylık |
| Sızma testi | Yıllık |
| Felaket kurtarma tatbikatı | Yıllık |
| Üçüncü taraf servis ücretleri (WhatsApp, İYS, OCR) | Kullanıma göre |

> **"Aylık 40 dolara çalışır" cümlesi yanlış.** Altyapı 40 dolar, sistem değil. Ticari teklifte iki tablo ayrı gösterilmeli.

---

## 17. Riskler

| Risk | Etki | Önlem |
|---|---|---|
| **Kapsam kayması** | Yüksek | İmzalı kapsam belgesi (§4.3 filtresi); her yeni istek Faz 3 kuyruğuna, ayrı fiyatla |
| **Ekip sisteme girmez, Excel'e döner** | Yüksek | Mobil "bugün aranacaklar", 3 tıkta kayıt, 5-6. haftada pilot, ilk ay Excel'i resmen kapat |
| **Evrak kaybı / yanlış sürüm** | Yüksek | Sürümleme + hash + denetim kaydı + ayrı depolama + yedek tatbikatı |
| **KVKK / İYS ihlali** | Yüksek | İzin-ret alanları v1'de, TR lokasyon + alt işleyen envanteri, indirme logu, imha politikası, hukuk onayı |
| **Statülerin sık değişmesi** | Orta | DB tabanlı statü/geçiş (K-05) |
| **e-TUYS entegrasyon beklentisi** | Orta | Bugün yazılı olarak "kamuya açık API bulunamadı"; alternatif olarak alan eşlemesi + başvuru paketi (K-08) |
| **Takvim aşımı** | Orta | Pilot ve üretim tarihleri ayrı taahhüt; haftalık demo |
| **Ofis makinesinde barındırma** (K-10) | Orta | UPS + ofis dışı şifreli yedek + tünel (port açma yok) + adanmış makine. İnternet kesintisi kabul edilmiş risk; sorun olursa Docker Compose olduğu gibi VPS'e taşınır |
| **Tek geliştirici bağımlılığı** | Orta | Yaygın yığın, README + kurulum betiği, kod deposu müşteride |
| **Program mevzuatının değişmesi** | Orta | Program sürümleme (K-09) |

---

## 18. Açık sorular — kodlama öncesi cevaplanmalı

1. **Ekip hangi dili biliyor?** → K-03 yığın seçimi buna bağlı.
2. **Kaç kullanıcı, yılda kaç dosya?** → şimdilik 15 kullanıcı / 500 dosya varsayıldı.
3. **İlk sürümde hangi destek programları?** → Faz 0 çıktısı, gerçek evrak listeleriyle.
4. **Mevcut veri var mı?** (Excel / eski CRM) → ayrı iş kalemi, ayrı fiyat.
5. **Şirket e-postası nerede?** (Google Workspace / M365) → SSO ve e-posta iliştirme kolaylaşır.
6. **Ücretlendirme nasıl?** Sabit mi, başarı primi mi, onaylanan tutarın yüzdesi mi? Ara ödeme? → "yüzde" ise küçük bir hakediş/tahsilat takibi gerekir (muhasebe modülü **değil**: sözleşme tutarı, başarı primi, hakediş, fatura durumu, tahsilat durumu). **Tutarı PM görecek mi?** — yetki matrisindeki tek gri alan.
7. **Belge hacmi ne?** → OCR'ın fazını belirler: ayda kaç belge, e-posta mı WhatsApp mı, kaçı taranmış görüntü, yanlış sınıflandırmanın maliyeti, belgeler dış AI servisine gönderilebilir mi (KVKK).

---

## 18.5 Geliştirme yürütmesi

Kod, **iş paketleri** halinde yazılır. Her paket ayrı bir agent'a verilir, ayrı dalda geliştirilir, ayrı PR olarak incelenir.

- **Ortak bağlam (her agent önce bunu okur):** `AGENTS.md` — depo kökünde
- **Paket tanımları:** depoda tutulmaz, sohbette verilir. Depo yalnızca kalıcı belgeleri taşır
- **Depo:** `https://github.com/MepCity/danismanlik_crm` — ⚠️ şu an **public**, gerçek müşteri verisi ve sır asla commit edilmez
- **Toplam:** 21 paket, 5 faz (Temel · Veri · Domain · Arayüz · Operasyon)

Kural: bir paket incelenip PR'ı onaylanmadan sonraki pakete geçilmez. Kapsam kaymasına ve mimari savrulmaya karşı asıl koruma bu.

---

## 19. Sonraki adımlar

1. §18'deki 7 soruyu müşteri/ekiple netleştir
2. **Faz 0 kabul kriterlerini yaz** — "atölye yapıldı" değil, ölçülebilir çıktı: statü listesi onaylandı · en az 3 programın gerçek evrak listesi girildi · rol/izin matrisi imzalandı · polymorphic subject deseni seçildi (§10.0) · veri göçü kapsamı belirlendi
3. Yığın kararını ver (K-03) + framework sürümü için teknik spike
4. Faz 0 atölyesini planla
5. **Ticari teklif ve kapsam belgesi** hazırla — bu teknik plandan ayrı, 3–4 sayfa: kapsam / teslim tarihleri / fiyat / kapsam dışı kalemler / kabul kriterleri. *Kapsam kaymasına karşı tek gerçek koruma o belgenin imzalanmış olması.*
6. Depo kur, CI/CD, staging ortamı
7. Faz 1'e başla

> **Migration yazmadan önce kapatılması gereken 4 madde** (§21 v1.2'de çözüldü, kod yazılırken doğrulanacak): iş akışı revizyonu + yetim kontrolü (K-09) · append-only izin defteri (§12.3) · tetikleyici session context (§8.2) · polymorphic subject + eksik tablolar (§10.0, §10.1).

---

## 20. Kaynaklar

- [E-TUYS Yatırımcı Bilgileri Giriş Kılavuzu — Sanayi ve Teknoloji Bakanlığı](https://sanayi.gov.tr/assets/pdf/destek-tesvikler/ETUYSYatirimciBilgileriGirisKilavuzu4.pdf)
- [KOSGEB Yeşil Sanayi Destek Programı](https://www.kosgeb.gov.tr/site/tr/genel/destekdetay/9022/yesil-sanayi-destek-programi)
- [KVKK — Aydınlatma yükümlülüğü kamuoyu duyurusu](https://www.kvkk.gov.tr/Icerik/6765/AYDINLATMA-YUKUMLULUGUNUN-YERINE-GETIRILMESI-HAKKINDA-KAMUOYU-DUYURUSU)
- [KVKK — Üçüncü kişilerden elde edilen verilerin pazarlamada kullanılması](https://www.kvkk.gov.tr/Icerik/8830/ucuncu-kisilerden-elde-edilen-kisisel-verilerin-reklam-ve-pazarlama-amacli-kullanilmasina-iliskin-kamuoyu-duyurusu)
- [KVKK — Yurt dışına aktarımda standart sözleşmeler](https://www.kvkk.gov.tr/Icerik/8170/Yurt-Disina-Veri-Aktariminda-Kullanilacak-Standart-Sozlesmelerde-Dikkat-Edilmesi-Gereken-Hususlara-Iliskin-Kamuoyu-Duyurusu)
- [Ticaret Bakanlığı — İleti Yönetim Sistemi](https://ticaret.gov.tr/ic-ticaret/ticari-elektronik-iletiler/ileti-yonetim-sistemi-iys)
- [PostgreSQL Row-Level Security](https://www.postgresql.org/docs/17/ddl-rowsecurity.html)
- [Frappe Framework](https://frappe.io/erpnext/framework)
- [MDN — PWA installable](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable)
- [AWS S3 — erişim yönetimi ve sürümleme](https://docs.aws.amazon.com/AmazonS3/latest/userguide/access-management.html)

---

## 21. Değişiklik günlüğü

| Sürüm | Tarih | Değişiklik |
|---|---|---|
| 1.15 | 16.08.2026 | “Dengeli” arayüz yönü kesinleştirildi: durağan yüzeylerde kenarlık önceliği korunurken etkileşimli kartlarda çok hafif yükselti, geçici katmanlarda tek kaplama gölgesi tanımlandı. Form ve tablo ritmi 40/38 px olarak ayrıldı; tek hareket eğrisi ve 120/170/220 ms süreleri benimsendi. Filament SPA gezinmesi, Docker içinde tekrarlanabilir ön yüz derlemesi ve dosya panosu/detayında pilot bilgi hiyerarşisi eklendi |
| 1.14 | 16.08.2026 | Program, çağrı dönemi/başvuru süresi ve gerekli belge listesi tek bir yönetim ekranında toplanır. `program_versions` ve `doc_templates` geçmiş dosyaların bağlı olduğu tanımı korumak için veri modelinde kalır ancak ayrı ana menüler olarak gösterilmez. Birleşik kayıtta program, güncel dönem ve belgeler tek transaction ile oluşturulur; düzenlemede listeden çıkarılan belge silinmez, pasifleştirilir |
| 1.13 | 16.08.2026 | Fırsat detayı sekmeler yerine Jira benzeri tek sayfalık çalışma alanına dönüştürüldü. Temel bilgiler, kişiler ve görüşmeler sol akışta; statü, sahip, program ve takip bilgileri sağ ayrıntı panelinde; yorumlar ile okunabilir aktör geçmişi alttaki birleşik Etkinlik alanında Yorumlar / Geçmiş / Tümü filtreleriyle sunulur. Ham `audit_log` kullanıcı arayüzüne taşınmaz |
| 1.12 | 15.08.2026 | Müşteri portalının olmayacağı kesinleştirildi. Yorum görünürlüğü seçimi kaldırıldı; tüm yeni yorumlar ve yanıtlar sunucu tarafında yalnız iç not olarak kaydedilir. Geçmiş kayıtlar denetim bütünlüğü için silinmez veya geriye dönük değiştirilmez |
| 1.11 | 15.08.2026 | Belge listesinden yetkili kullanıcıların yeni belge sürümü yüklemesi ve güvenli sürümleri tek tek indirmesi görünür hale getirildi. Dosya genelindeki toplu indirme, her evrakın yalnızca son temiz sürümünü süreli imzalı bağlantıyla tek ZIP içinde sunar; bekleyen, zararlı veya silinmiş sürümler pakete alınmaz ve talep/indirme denetim akışına yazılır |
| 1.10 | 15.08.2026 | Takip panosundaki açık fırsatlar aktif huni statüleri arasında ileri ve geri sürüklenebilir hale getirildi. Geri hareketler de statü makinesi, yetki kontrolü, statü geçmişi ve aktör denetiminden geçer; iş alındı, kaybedildi ve aranmak istemiyor gibi terminal statüler güvenlik gereği kapalı kalır |
| 1.9 | 14.08.2026 | Veri kaynağı kullanıcı arayüzünden tamamen kaldırıldı. Yeni potansiyel müşteri ve firma kişisi kayıtlarında kaynak, kullanıcıya sorulmadan işlem bağlamından otomatik yazılır; mevcut KVKK/denetim geçmişi korunur |
| 1.8 | 14.08.2026 | Görev son tarihi isteğe bağlı hale getirildi. Başlık ve atanan kişi görev için yeterlidir; son tarihi olmayan görevler açık görev listesinde tutulur, hatırlatma zamanı son tarihten bağımsız verilebilir |
| 1.7 | 13.08.2026 | Pazarlama veri girişi sadeleştirildi: il kodu yerine il seçimi; karardaki rol, aydınlatma yöntemi ve görüşme süresi kaldırıldı. Fırsat panosu “Takip panosu” oldu; takip ve dosya panolarına doğrudan geçiş için sürükle-bırak ve kart detay çekmecesi eklendi. Bugün aranacaklar ekranı yoğun satır düzenine geçirildi |
| 1.6 | 13.08.2026 | Operasyon arayüzü “sakin operasyon merkezi” tasarım sistemine geçirildi: ortak marka kabuğu, mavi-gri yüzey hiyerarşisi, tek indigo vurgu, 36 px operasyon yoğunluğu, açık/koyu tema, 120–240 ms mikro hareket sözleşmesi, hareket azaltma desteği ve mobil kırılım kuralları kalıcılaştırıldı |
| 1.5 | 12.08.2026 | Ana akışın kayıt granülerliği kesinleştirildi: firma ana kayıt; statü fırsat/proje seviyesinde; görüşülen kişi ve karardaki rol açık bağ; firma geneli işbirliği öznesi; aynı firmada aynı/farklı program için bağımsız çoklu proje. Tek ekran potansiyel müşteri girişi, Firma 360° ve atama iş istasyonu bu karara göre hizalandı |
| 1.0 | 10.08.2026 | İlk teknik fizibilite. Web+PWA, sıfırdan geliştirme, DB tabanlı statü, program sürümleme, e-TUYS tespiti |
| 1.4 | 12.08.2026 | Ürün adı **Bizlife CRM** olarak belirlendi. TOTP müşteri talebiyle isteğe bağlı yapıldı; Şirket Yetkilisi hesabının geniş erişim riski bilinerek kabul edildi |
| 1.1 | 10.08.2026 | İkinci bağımsız rapor + karşılıklı değerlendirme sonrası: takvim düzeltmesi (pilot/üretim ayrımı, 11–15 hafta) · `status_history` tablosu · `interactions` ayrıldı · belge deposu SPOF düzeltmesi · Media Library revizyon iddiası düzeltildi · İYS izin/ret alanları v1'e · KVKK alt işleyen envanteri · e-TUYS ifadesi yumuşatıldı · altyapı/TCO ayrımı · RLS ertelendi · rol sayısı 4'e indi · dosya statüsü 10'a indi · kopyalama+referans mekanizması · iş akışı sürümleme reddedildi |
| 1.3 | 10.08.2026 | **K-10 eklendi** — barındırma ofisteki 7/24 çalışan makinede yapılacak. Pilot için koşulsuz uygun; üretim için altı şart (UPS · ofis dışı şifreli yedek · port açma yerine tünel · adanmış makine · mirror disk · fiziksel güvenlik). Hibrit öneri: uygulama+DB ofiste, nesne depolama ve yedek bulutta. Maliyet tablosu iki sütuna ayrıldı. Açık soru: makine kimin ofisinde — sizinkiyse veri işleyen sözleşmesi gerekir |
| 1.2 | 10.08.2026 | Üçüncü tur — plan denetimi sonrası **7 bulgunun tamamı kabul edildi.** **P1:** K-09 yeniden yazıldı (değişmez akış revizyonu + yürürlük tarihi + **yetim geçiş kontrolü** — v1.1'de yalnız statüler korunuyordu, asıl açık geçişlerdeydi) · `communication_consents` **append-only izin defteri** eklendi, `contacts` yalnız özet taşır · **tetikleyici session context** (`SET LOCAL app.actor_id`, doğrudan SQL → `system/unknown`) + hassas alanların denetim dışı bırakılması · veri modeli metinle hizalandı: `files.deal_document_id` FK, `comments`/`tasks`/`activities` polymorphic subject, yeni tablolar `teams`, `team_members`, `notifications`, `outbox`, `workflow_revisions`, `role_permission_history`. **P2:** koşul kalkınca belge **silinmez**, PM onaylı "gerekli değil" önerisi üretilir · imzalı URL ≠ indirme, iki ayrı olay + opaque UUID anahtar · sistem yöneticisi ≠ iş verisi erişimi (break-glass) · kapsam filtresi iş özelliği / zorunlu güvenlik-mevzuat gereksinimi diye ayrıldı · **efor aritmetiği düzeltildi** (49–68 geliştirme + tampon = 55–75) · framework sürümü sabitlenmedi · Tauri "1 gün" taahhüdü kaldırıldı · maliyetler "varsayımsal, KDV ve yönetilen hizmet hariç" işaretlendi |
