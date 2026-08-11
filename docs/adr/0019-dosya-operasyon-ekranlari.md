# ADR-0019: Dosya operasyon ekranları

- **Durum:** Kabul edildi
- **Tarih:** 14.08.2026
- **İlgili:** PLAN.md K-03, K-05, K-07, K-11, §5.3, §6, §11

## Bağlam

Dosya panosu, dosya detayı ve evrak checklist ekibin gün içinde tekrar tekrar kullandığı operasyon ekranlarıdır. Filament Resource tablosu bu üç yüzeyin kanban, sekmeli bağlam ve satır içi belge kararlarını tek iş akışında vermesi için yeterli değildir. Buna karşılık iş kurallarını Livewire bileşenine taşımak K-03'ü ihlal eder ve daha sonra mobil/portal istemcilerinin aynı kuralları tekrar yazmasına yol açar.

## Karar

### Pano okuma modeli

Pano, Filament paneli içinde özel bir `Page`/Livewire bileşenidir. Sütunlar etkin `deal` statülerinden sıra alanıyla okunur; statü kodu veya etiketi arayüzde sabitlenmez. Kullanıcının dosya kümesi önce `ScopedQuery` ile daraltılır. Firma, program, PM ve statü tek eager-load ile; belge sayaçları ise `deal_documents` üzerinde koşullu `withCount` alt sorgularıyla hesaplanır.

`geldi`, `eksik`, `inceleniyor` ve `süresi doldu` değerleri dosya statüsü değildir ve kalıcı bir özet kolona yazılmaz. Her pano okumasında belge satırlarından türetilir. Bekleyen gereklilik önerisi de `document_requirement_suggestions.status = pending` üzerinden sayılır. Statüde geçen süre `deals.status_changed_at` önbelleğinden hesaplanır. Görsel gecikme eşiği statü koduna bağlı değildir; varsayılan yedi gün `operations.delayed_status_days` ayarındadır.

### Sürükle-bırak

Bu pakette sürükle-bırak eklenmedi. Geçiş guard/condition reddinde istemci tarafı iyimser güncellemeyi güvenle geri alma, klavye erişilebilirliği ve yatay kanban kaydırması birlikte çözülmeden kart taşımak veri kaybı algısı üretir. Statü değişimi dosya detayındaki açık aksiyonlarla mevcut `StatusMachine` üzerinden yapılır. Reddedilirse domain istisnasının mesajı değiştirilmeden gösterilir.

### Checklist aksiyonları

Checklist ayrı bir domain akışı kurmaz. Yükleme, inceleme/karar, indirme, ad-hoc belge ve gereklilik önerisi kararları sırasıyla mevcut `DocumentUploadService`, `DocumentStatusService`, `DocumentAccessService`, `AdHocDocumentService` ve `DocumentRequirementDecisionService` çağrılarıdır. Livewire yalnız form doğrulaması, policy/kapsam kapısı ve geri bildirim taşır. Ret ile yeni sürüm isteğinde gerekçe hem formda hem serviste zorunludur.

Dosya sürümleri satır altında açılır geçmişte gösterilir; eski sürümler silinmez. İndirme bağlantısı yalnız mevcut erişim servisi üzerinden ve policy sonrasında üretilir. Aksiyon görünürlüğü kullanıcı deneyimi içindir; aynı yöntem doğrudan çağrıldığında sunucu tarafı kontrol yeniden uygulanır.

Eksik evrak e-postası, kabul edilmiş veya gerekli değil durumunda olmayan zorunlu satırlardan üretilir. Yalnız aktif, birincil ve e-posta izni bulunan firma kontağı hedef olabilir. WP-13 kuyruğunun aynı bildirim/iş modeli kullanılır; `notifications` satırı sistem kullanıcısı veya harici e-posta alıcısından tam birini taşır. Yeni bir bildirim servisi oluşturulmaz.

## Sonuçlar

- Operasyon ekranları tek panel ve tek tasarım token kaynağını kullanır; ayrı ön yüz veya yeni bağımlılık yoktur.
- Sayaç sorgularının anlamı değişirse pano testi `5/8 · 2 · 1 · 1` örneğini korur.
- Statü geçişleri ve belge kararlarının ikinci istemcisi mevcut domain servislerini doğrudan kullanabilir.
- Sürükle-bırak daha sonra eklenirse `StatusMachine` reddinde geri alma ve erişilebilir klavye akışı birlikte kabul kriteri olur.
