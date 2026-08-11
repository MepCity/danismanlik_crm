# ADR 0016 — İşbirliği ve aktivite okuma kararları

## Durum

Kabul edildi — 12.08.2026

## Kararlar

### Bahsetme kapsamı

Bahsetmeler gövdede `@[Görünen Ad](user:123)` biçiminde taşınır. Sunucu yalnız etkin
ve yorumun öznesini görmeye yetkili kullanıcıları `mentions` anlık görüntüsüne alır.
Kapsam dışı kullanıcı için bildirim oluşturulmaz. Böylece yorum metni veya özne bilgisi
yetki sınırının dışına taşınmaz.

### Sonra aranacak kaydı ve görev ilişkisi

`Sonra aranacak` akışı otomatik olarak ikinci bir `tasks` satırı üretmez.
`leads.next_call_at` ile `leads.owner_user_id` bu taahhüdün tek gerçek kaynağıdır;
aynı tarih ve sorumluyu görevde de tutmak iki kaydın ayrışmasına yol açar. Görevler,
bu akıştan bağımsız ek iş gerektiğinde kullanıcı tarafından açılır. Ana panel ve günlük
özet, geri aramaları doğrudan lead alanlarından okuyacaktır.

### Tamamlanan görevi yeniden açma

Yetkili kullanıcı tamamlanan görevi yeniden açabilir. Yeniden açma yeni satır üretmez;
`completed_at` temizlenir ve olay aktivite akışına yazılır. Silme yoktur ve bütün
değişiklikler trigger tabanlı denetimde kalır.

### E-posta yeniden deneme politikası

E-posta işi üç kez denenir; beklemeler 60 ve 300 saniyedir. Her hata bildirim satırını
`failed` yapar ve hata metnini kaydeder. Sonraki deneme başında satır tekrar `pending`,
başarılı teslimde `sent` olur. Üç deneme sonunda kalıcı hata `failed` olarak kalır.

### Aktivite çevirisi

Aktivite satırları olay adına göre `lang/tr` şablonlarıyla çevrilir. Çevirmen yalnız
payload içindeki olay-anı etiketlerini ve zaman tüneli sorgusunun toplu yüklediği özne
etiketini kullanır; güncel statü tablosuna geri dönmez. Bilinmeyen olaylar ham JSON
yerine güvenli bir yedek cümle üretir. `automation` ve aktörsüz kayıtların görünen
aktörü `Sistem`dir.
