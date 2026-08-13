# ADR-0027 — Operasyon arayüzü tasarım sistemi

**Durum:** Kabul edildi  
**Tarih:** 13.08.2026

## Bağlam

Uygulamanın iş akışı tamamlanmış olsa da Filament varsayılanlarına yakın kalan
ekran kabuğu, özel operasyon sayfalarıyla yönetim kaynakları arasında görsel ve
etkileşimsel tutarsızlık oluşturuyordu. Gün içinde yoğun kullanılan ekranların
hızlı okunması, buna karşılık ürünün genel CRM veya ERP izlenimi vermemesi
gerekiyor.

## Karar

Arayüzün kalıcı yönü **sakin operasyon merkezi** olarak belirlenmiştir.

- Tek indigo vurgu rengi ve hafif mavi eğilimli nötr skala kullanılır.
- Katman ayrımı gölgeyle değil bir piksellik kenarlık ve yüzey tonuyla yapılır.
- Form, tablo ve pano yoğunluğu 36 piksellik temel satır ritmini korur.
- Marka, navigasyon, başlık, form, tablo, kart, sekme ve durum bileşenleri aynı
  token ve etkileşim sözleşmesini kullanır.
- Etkileşim geçişleri 120–240 ms aralığındadır; hareket azaltma tercihi bütün
  animasyon ve geçişleri etkisizleştirir.
- Açık ve koyu tema ayrı yüzey tokenları taşır. Durum renkleri vurgu renginden
  bağımsızdır ve renk yanında biçimle de ifade edilir.
- Masaüstü iki sütunlu iş istasyonları dar ekranda tek sütuna iner; temel işlem
  kontrolleri tam genişlikte ve dokunmaya uygun kalır.

## Sonuçlar

Yeni arayüz bileşenleri önce `tokens.css` sözlüğünü genişletir ve çiğ renk
değeri içermez. Tasarım sözleşmesinin odak görünürlüğü, hareket azaltma desteği,
operasyon yoğunluğu ve token kullanımı otomatik testlerle korunur. Yeni bir ön
yüz bağımlılığı eklenmemiştir; mevcut Filament ve Blade yüzeyi özelleştirilir.
