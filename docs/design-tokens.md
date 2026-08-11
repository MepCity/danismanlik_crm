# WP-14 tasarım tokenları

Tek renk kaynağı `resources/css/filament/operations/tokens.css` dosyasıdır. Arayüz bileşenleri renk değeri yazmaz; yalnız `--crm-*` değişkenlerini kullanır. Filament'in `primary` ve `gray` ölçekleri de bu dosyada CRM tokenlarına bağlanır.

## Renk

| Rol | Açık tema | Koyu tema | Kullanım |
|---|---|---|---|
| Vurgu 500 | `#3867f4` | `#7d9af0` | Birincil eylem ve aktif seçim |
| Sayfa | `#f7f9fc` | `#101720` | Ana zemin |
| Panel | `#ffffff` | `#171f2a` | Kart, tablo ve yan menü |
| Kenarlık | `#dce3ec` | `#303d4e` | 1 px ayrım; gölge yerine |
| Metin | `#1b2430` | `#e8edf4` | Ana içerik |
| İkincil metin | `#4d5d73` | `#a9b5c5` | Açıklama ve yardımcı veri |

Durum ekseni vurgu renginden ayrıdır:

| Veritabanı tokenı | Biçim | Anlam |
|---|---|---|
| `neutral` | rozet + nötr sol şerit | Pasif veya genel |
| `info` | rozet + bilgi sol şeridi | Süreç bilgisi |
| `waiting` | rozet + bekleme sol şeridi | Kullanıcı/kurum bekleniyor |
| `success` | rozet + başarı sol şeridi | Tamamlandı/onaylandı |
| `danger` | rozet + risk sol şeridi | Gecikme, ret veya hata |

Renk tek başına anlam taşımaz. Durum gösteren her bileşen `.status-token` sınıfını, `data-status` niteliğini ve anlamlı bir ikon/etiketi birlikte kullanır.

## Tipografi ve yoğunluk

| Token | Değer | Kullanım |
|---|---|---|
| `--crm-font-ui` | Inter + sistem sans | Başlık, etiket, gövde; tek UI font kaynağı |
| `--crm-font-data` | sistem monospace | Tutar, sayaç, kod, tarih-saat |
| `--crm-text-xs/sm/base/lg/xl` | 12 / 13 / 14 / 16 / 20 px | Sabit tipografik ölçek |
| `--crm-table-divider-width` | 1 px | Satırlar arası kenarlık; satır yüksekliği hesabına dahildir |
| `--crm-row-height` | 36 px | Gövde hücresinin `.fi-ta-col` sarmalayıcısı |
| `--crm-table-header-height` | 32 px | Tablo sütun başlığı |
| `--crm-pagination-height` | 40 px | Tablo sayfalama çubuğu |
| `--crm-space-1/2/3/4/6` | 4 / 8 / 12 / 16 / 24 px | Aralık ölçeği |

UI fontu yalnız `tokens.css` içindeki `--crm-font-ui` ile seçilir; uygulama CSS'i veya Vite yapılandırması ikinci bir font ailesi tanımlamaz. Sayısal hücreye `.numeric-data` verilir; bu rol monospace yazı ve `tabular-nums` kullanır. Tablo sayfalaması 25, 50 ve 100 satırdır; varsayılan 25'tir.

Filament tablo hücrelerinin gerçek dikey dolgusu `<td>` üzerinde değil, `.fi-ta-col` içindeki sütun bileşenlerindedir. Tema bu iç dolguyu sıfırlar ve minimum yüksekliği `.fi-ta-col` üzerinde uygular. Böylece `--crm-row-height` içerik tek satır kaldığı sürece render edilen satır yüksekliğini doğrudan belirler; uzun veya çok satırlı içerik erişilebilir biçimde büyümeye devam eder.

Durum tokenlarının gerçek rozetlerde renk ve form birlikteliği WP-16 dosya ekranlarında kanıtlanacaktır. WP-14 örnek Firma tablosundaki `is_active` alanı yalnız boolean ikondur ve semantik rozet kabul edilmez.

## Yüzey ve boş durum

Kart/panel ayrımı `--crm-border` ile 1 px kenarlık ve ayrı yüzey tonuyla yapılır; tasarım sistemi gölge kullanmaz. Boş tablolar, ikincil yüzey üzerinde kesik kenarlıklı tek bir boş durum alanı gösterir. Başlık yapılacak işi söyler, açıklama neden boş olduğunu belirtir; oluşturma yetkisi yoksa eylem gösterilmez.

## Navigasyon iskeleti

PLAN.md §11 ekranları aşağıdaki gruplara yerleşir. WP-14 yalnız grupları ve örnek Firma Resource'unu açar; ekran içerikleri sonraki paketlerindir.

| Grup | Ekranlar | Paket |
|---|---|---|
| Genel Bakış | Ana panel | WP-19 |
| Pazarlama | Arama listesi, kanban | WP-17 |
| Firmalar | Firma 360° | WP-17 |
| Dosya Operasyonları | Dosya detayı, dosya panosu, atama, belge inceleme | WP-16 |
| İş Takibi | Görev/takvim, bildirim merkezi | WP-18 |
| Raporlama | Raporlar | WP-19 |
| Yapılandırma | Program, kullanıcı/rol, statü/geçiş | WP-15 |

Yeni Resource sınıfları doğrudan Filament `Resource` sınıfından değil `App\Filament\Resources\ScopedResource` sınıfından türetilir. Bu sınıf `getEloquentQuery()` metodunu sonlandırır; liste sorgusunu `ScopedQuery` dışına çıkarmak mümkün değildir.
