# Zoho esinli Bizlife tasarım dili

## Amaç

Zoho CRM'in tutarlı ve operasyonel çalışma kalıplarını Bizlife'ın dar kapsamına uyarlamak. Bu belge birebir görsel kopya tarif etmez; yeni ekranların ortak davranış sözleşmesini tanımlar.

## Uygulama kabuğu

- Sol navigasyon koyu lacivert ve kompakttır.
- Ana içerik açık temada beyaz/mavi-gri yüzey, koyu temada ayrı tasarlanmış koyu yüzey kullanır.
- Global arama, bildirim ve profil sistem çapındaki üst araçlardır.
- Birincil eylem mavidir ve sayfa başlığının sağında bulunur.

## Liste ekranı

1. Sayfa başlığı ve kısa açıklama
2. Görünüm ve filtre özeti
3. Filtre, sıralama ve yenileme araçları
4. Yoğun tablo veya iş gerektiriyorsa Kanban
5. Toplam kayıt ve sayfalama

Tablo satırı yaklaşık 38 px ritmindedir. Firma/fırsat/dosya listelerinde bir masaüstü görünümüne 25–30 satır sığmalıdır. Filtreler üst araç şeridinde başlar; gelişmiş filtreler yan panelde açılabilir.

## Kayıt detayı

- Üstte kayıt adı, ikincil kimlik, sahip ve bağlamsal eylemler
- Ana alanda özet ve iş içeriği
- Sağda sorumlu, statü, program ve tarihler
- Aşağıda yorum, görüşme ve değişiklikleri birleştiren Etkinlik
- İlişkili bölümler sekme kalabalığı yerine sayfa içi bağlantı veya kompakt görünüm seçici kullanır

## Formlar

- Sık kullanılan alanlar ilk bölümde görünür.
- İkincil/vergi/ölçek alanları ayrı “Kurumsal ayrıntılar” bölümündedir.
- Etiketler kullanıcı dilindedir; teknik kolon adı gösterilmez.
- Tek ana “Kaydet” eylemi vardır.
- Doğrulama mesajı alanın ne istediğini açıklar ve ilgili alana bağlanır.

## Hareket

- Hover/focus: 120 ms
- Panel/filtre/drawer: 170–220 ms
- Hareket yalnız odak ve katman değişimini açıklar.
- `prefers-reduced-motion` bütün hareketleri etkisizleştirir.

## Kopyalanmayacak Zoho özellikleri

- Genel amaçlı onlarca modül
- İlk kayıtta çok uzun formlar
- Aynı işi yapan birden fazla ana buton
- Çok katmanlı ayar ve teamspace yapısı
- Ürün, stok, fatura, CPQ ve sosyal medya yüzeyleri

## Firma ve müşteri ekranlarına uygulama

- Firma Rehberi tüm firmaları yoğun tabloda gösterir; sektör ve il ana filtrelerdir.
- Müşteriler yalnız operasyon dosyası bulunan firmaların türetilmiş görünümüdür.
- “Firma ekle” ve “Müşteri akışı başlat” kendi ekranlarının tek ana eylemidir.
- Firma detayında kişiler, fırsatlar, müşteri işleri ve firma yorumları aynı bağlam içinde kalır.
