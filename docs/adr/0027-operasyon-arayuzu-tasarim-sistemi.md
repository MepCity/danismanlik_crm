# ADR-0027 — Operasyon arayüzü tasarım sistemi

**Durum:** Kabul edildi  
**Tarih:** 13.08.2026

**Son revizyon:** 16.08.2026

## Bağlam

Uygulamanın iş akışı tamamlanmış olsa da Filament varsayılanlarına yakın kalan
ekran kabuğu, özel operasyon sayfalarıyla yönetim kaynakları arasında görsel ve
etkileşimsel tutarsızlık oluşturuyordu. Gün içinde yoğun kullanılan ekranların
hızlı okunması, buna karşılık ürünün genel CRM veya ERP izlenimi vermemesi
gerekiyor.

## Karar

Arayüzün kalıcı yönü **sakin operasyon merkezi** olarak belirlenmiştir.

- Tek indigo vurgu rengi ve hafif mavi eğilimli nötr skala kullanılır.
- Durağan katmanlar bir piksellik kenarlık ve yüzey tonuyla ayrılır. Yalnız
  etkileşimli kartın üzerine gelindiğinde çok hafif yükselti; menü, modal ve
  çekmece gibi geçici katmanlarda tek bir kaplama gölgesi kullanılabilir.
- Form kontrolleri 40, yoğun tablo satırları 38 piksellik ritim kullanır.
- Marka, navigasyon, başlık, form, tablo, kart, sekme ve durum bileşenleri aynı
  token ve etkileşim sözleşmesini kullanır.
- Etkileşimler tek hareket eğrisiyle 120/170/220 ms sürelerini kullanır;
  hareket azaltma tercihi bütün animasyon ve geçişleri etkisizleştirir.
- Filament SPA gezinmesi tam sayfa parlamasını azaltır. Destekleyen
  tarayıcılarda görünüm geçişi aynı kısa hareket sözleşmesine uyar.
- Açık ve koyu tema ayrı yüzey tokenları taşır. Durum renkleri vurgu renginden
  bağımsızdır ve renk yanında biçimle de ifade edilir.
- Masaüstü iki sütunlu iş istasyonları dar ekranda tek sütuna iner; temel işlem
  kontrolleri tam genişlikte ve dokunmaya uygun kalır.

## Sonuçlar

Yeni arayüz bileşenleri önce `tokens.css` sözlüğünü genişletir ve çiğ renk
değeri içermez. Tasarım sözleşmesinin odak görünürlüğü, hareket azaltma desteği,
operasyon yoğunluğu ve token kullanımı otomatik testlerle korunur. Yeni bir ön
yüz bağımlılığı eklenmemiştir; mevcut Filament ve Blade yüzeyi özelleştirilir.
Vite ve Filament varlıkları host makinede Node kurulmasını gerektirmeden Docker
içinde tekrarlanabilir biçimde derlenir. Dosya panosu ile dosya detayı, bu
sözleşmenin bilgi hiyerarşisi ve mikro etkileşimlerini doğrulayan ilk pilot
ekranlardır; yön uygun bulunmazsa dal/PR seviyesinde geri alınabilir.
