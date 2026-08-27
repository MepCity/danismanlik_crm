# ADR-0033: Firma listesinden filtreli e-posta

- Durum: Kabul edildi
- Tarih: 27.08.2026
- Kaynak: Müşteri kararı

## Bağlam

Firma ekranındaki “Fırsat aç / arama planla” ifadesi tek bir günlük işi iki
ayrı kavram gibi gösteriyordu. Toplu e-posta ise önce tablo satırlarının elle
seçilmesini, sonra ayrı yönetim ekranında hazırlanmış bir şablonun seçilmesini
gerektiriyordu. Kullanıcının gerçek akışı önce firma listesini süzmek, hedef
kitleyi gözden geçirmek ve iletiyi aynı yerde yazmaktır.

## Karar

1. Firma listesi ve detayındaki giriş “Arama planla” adını taşır. Arama zamanı
   zorunludur; mevcut `Lead`, statü geçmişi ve takip panosu altyapısı korunur.
2. Ayrı E-posta şablonları navigasyonu ve sayfa erişim izni pasifleştirilir.
   Geçmiş şablon satırları denetim bütünlüğü için silinmez.
3. Firma listesindeki etkin görünüm, arama ve filtreler toplu e-posta hedef
   kitlesini otomatik doldurur. Gönderim penceresindeki aranabilir çoklu seçim
   ile kullanıcı filtre sonucundan firma çıkarabilir veya erişebildiği başka
   bir firmayı ekleyebilir.
4. Konu ve gövde gönderim penceresinde yazılır. Desteklenen değişkenler ve
   gerçek alıcı önizlemesi aynı pencerede kalır.
5. Gönderim kapsamı sunucu tarafında yeniden `ScopedQuery` ve `CompanyPolicy`
   üzerinden çözülür. Güncel e-posta izni, imzalı abonelikten çıkma bağlantısı,
   kuyruk ve filtre anlık görüntülü aktivite kaydı değişmez.

## Sonuçlar

- Hedef kitle filtreyle tek adımda oluşur; istisnalar aynı pencerede yönetilir.
- Kullanıcı ayrı bir şablon yönetimi ekranına gitmez.
- “Arama planla” sade bir kullanıcı eylemidir; fırsat/statü veri modeli ve
  raporlama sürekliliği korunur.
- Eski şablon verileri ve izin satırı silinmez; sayfa izni geri alınabilir bir
  migration ile pasifleştirilir.
