# ADR-0028 — Pazarlama formu ve pano etkileşimi

**Durum:** Kabul edildi  
**Tarih:** 13.08.2026

## Bağlam

İlk müşteri girişinde il kodu, karardaki rol, aydınlatma yöntemi ve görüşme
süresi operasyon ekibine ölçülebilir bir fayda sağlamadan formu uzatıyordu.
Ayrıca fırsat ve dosya panoları durumu gösteriyor, fakat günlük ilerletme için
kart detayına girilmesini gerektiriyordu. Bugün aranacaklar ekranındaki bağımsız
kartlar da yoğun bir arama gününü taramayı zorlaştırıyordu.

## Karar

- Firma ili 81 geçerli il adından seçilir; plaka kodu kullanıcı arayüzünde ve
  kalıcı veri modelinde tutulmaz.
- Kişide yalnız şirket içi unvan tutulur. Karardaki rol alanı kaldırılır.
- İzin kaydında aydınlatma tarihi korunur; yöntem alanı kaldırılır. Görüşme
  satırından süre kaldırılır.
- Fırsat ekranının adı **Takip panosu** olur. Takip ve dosya kartları yalnız
  veri tabanında tanımlı doğrudan geçişlere sürüklenebilir.
- Hedef statünün zorunlu alanı varsa bırakma işlemi ilgili tamamlayıcı formu
  açar. Geçiş koşulları ve yetkiler domain servislerinde denetlenmeye devam eder.
- Kart tıklaması sağ tarafta hızlı detay açar; tam kayıt ekranına bağlantı
  korunur.
- Bugün aranacaklar ekranı, sabit kolon ritmine sahip yoğun bir çalışma listesi
  olur. İzin engeli hem biçim hem metinle gösterilir.

## Sonuçlar

Veri girişindeki bilişsel yük azalır ve il değerleri raporlamaya hazır, okunur
adlarla saklanır. Sürükle-bırak bir yetki veya iş kuralı kestirmesi değildir;
mevcut `TransitionLead`, `StatusMachine` ve `AssignDeal` servislerinin yeni bir
arayüz giriş noktasıdır. Geçersiz hedef reddedilir, zorunlu veri gerektiren
hedef doğrudan yazılmaz.
