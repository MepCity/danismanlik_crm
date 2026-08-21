# ADR-0029 — Zoho tasarım yönü

**Durum:** Kabul edildi  
**Tarih:** 21.08.2026

## Bağlam

İlk tasarım sistemi Linear, Attio ve Twenty'den sakinlik; Zoho CRM'den ise
yalnız operasyon yoğunluğu ve tekrar eden ekran iskeleti alıyordu. Bu ayrım
uygulama kabuğunu müşterinin beklediği tanıdık CRM çalışma dilinden uzak tuttu.
Kullanıcı, arayüzün kapsamı büyütülmeden görsel olarak da mümkün olduğunca
Zoho'ya yaklaşmasını istedi.

## Karar

Zoho CRM birincil mekanik ve görsel referanstır.

- Sol navigasyon koyu lacivert, çalışma yüzeyi açık mavi-gri, birincil eylemler
  mavidir. Koyu tema, aynı renklerin ters çevrilmesi değil ayrı yüzey
  hiyerarşisidir.
- Durağan yüzeyler büyük gölge yerine bir piksellik çizgi ve arka plan tonuyla
  ayrılır. Yarıçaplar küçüktür; tablo ve panel, karttan önce gelir.
- Kontroller 36, yoğun tablo satırları 38 piksel ritmindedir. Durumlar renk ve
  biçimle birlikte gösterilir.
- Hover, active, focus ve disabled durumları aynı token sözleşmesini kullanır.
  Geçişler 120/170/220 ms süreleriyle opacity ve küçük translate değişimleriyle
  sınırlıdır; hareket azaltma tercihi bunları kapatır.
- Kaynak listeleri görünüm seçici, filtre/sütun araçları, yenileme, oluşturma,
  yoğun tablo ve sayfalamayı aynı yerleşimde sunar.
- Detay sayfası üst kimlik ve süreç bağlamını, ana çalışma alanını, sabit sağ
  ayrıntı panelini ve okunabilir etkinlik geçmişini birlikte korur.

## Sonuçlar

Renklerin tamamı `tokens.css` üzerinden gelir; bileşen dosyalarında çiğ hex
değeri yasaktır. Linear / Attio / Twenty artık birincil referans değildir;
yalnız düşük görsel gürültü ve tek vurgu rengi ilkesi korunur. Zoho'nun ürün,
fatura, stok, kampanya ve genel amaçlı özelleştirme kapsamı alınmaz.
