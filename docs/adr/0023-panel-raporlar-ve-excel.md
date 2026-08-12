# ADR-0023: Panel, rapor metrikleri ve Excel akışı

- **Durum:** Kabul edildi
- **Tarih:** 12.08.2026

## Bağlam

Ana panel rol ve `own | team | all | none` kapsamına göre değişmeli; altı sabit
rapor görünümü aynı kapsam sözleşmesini Excel çıktısında da korumalıdır. Süre
metriklerinin pano önbelleğinden hesaplanması, dosya aynı statüye döndüğünde
önceki bekleme aralıklarını kaybettirir. Ham arama sayısı da tek başına
performans göstergesi olarak kolayca şişirilebilir.

## Karar

- Statüde toplam süre, `status_history.entered_at / exited_at` aralıklarının
  dosya ve statü bazında toplamıdır. Açık aralıkta sorgu zamanı kullanılır.
  `deals.status_changed_at` yalnız pano önbelleği olarak kalır.
- Evrak toplama süresi
  `deals.document_requested_at → deals.all_required_accepted_at` aralığıdır;
  statü etiketinden veya statü tarihinden türetilmez.
- Program başarı oranının gerçek kaynağı nullable
  `deals.result_outcome = approved | rejected` alanıdır. Bir statü etiketi
  “onay” kabul edilmez. Dönüşüm hunisi arama sayısını görüşme, iş alındı ve
  onay sayılarıyla birlikte gösterir; sıfır paydalar yüzde sıfır üretir.
- Müşteri dönüşü bekleyen kartı statü kodu/etiketi karşılaştırmaz;
  `statuses.awaits_customer_response` anlamsal bayrağını kullanır.
- Rapor görünürlüğü `report.view`, Excel indirme `report.export` iznidir.
  Ekranı görebilmek dosya indirme yetkisi vermez. Hem ekran hem Excel aynı
  domain rapor sorgularını ve `ScopedQuery` kapsamını kullanır.
- XLSX, OpenSpout ile geçici diske satır satır yazılır. Yaklaşık 500 aktif
  dosyalı mevcut ölçekte kuyruk kullanıcıya gereksiz bekleme ve bildirim
  altyapısı yükü getirir; disk akışı ise bellek tüketimini satır sayısından
  bağımsız tutar. Hacim veya üretim süresi operasyonel eşiği aşarsa aynı
  exporter kuyruk işi içinden çağrılabilir.
- Her tamamlanan üretim `report_exports` salt-ekleme kaydına aktör, sabit rapor
  kodu ve satır sayısıyla yazılır; tablo INSERT tetikleyicisi ayrıca DB
  `audit_log` kaydı üretir.
- Grafik eklenmedi. Altı görünümde karşılaştırmayı 36 px yoğunlukta tablolar ve
  renk + şekil kullanan kartlar daha açık anlatıyor. Gelecekte grafik gerekirse
  renkler WP-14 semantik durum tokenlarından gelir ve etiket/şekil olmadan
  anlam taşımaz.

## Sonuçlar

- Aynı statüye tekrar girişler doğru toplanır; bunun için
  `(deal_id, status_id, entered_at) INCLUDE (exited_at)` kısmi indeksi vardır.
- Kapsam dışı satır Excel'e sızmaz ve izinsiz indirme sunucu tarafında 403 olur.
- Sistem Yöneticisi `scope=none` ile iş kartı ve aktivite görmez; yapılandırma
  yetkileri değişmez.
- Özel rapor üreticisi, BI/küp katmanı ve grafik bağımlılığı eklenmez.
