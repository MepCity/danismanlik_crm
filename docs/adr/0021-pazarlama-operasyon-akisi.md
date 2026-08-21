# ADR-0021: Pazarlama operasyon akışı

## Durum

Kabul edildi

## Bağlam

Pazarlama ekibi sahada dizüstü açmadan günlük takip aramalarını tamamlamalı ve
her aramayı fırsattan ayrı kaydetmelidir. “İş alındı” geçişi fırsat sürecinin
bittiği ve programa bağlı dosya sürecinin başladığı tek kritik sınırdır.

Statüler, geçiş davranışları ve zorunlu evraklar koda gömülemez.

## Karar

“Bugün aranacaklar” ekranı yoğun, satır tabanlı çalışma düzenidir. `next_call_at` bugün
veya geçmişte olan, kullanıcının kapsamındaki fırsatlar alınır; en eski tarih
önce gelir. Kart firma, kişi, telefon, son görüşme sonucu ve arama sırasını
birlikte gösterir. Telefon `tel:` bağlantısıdır. Hazır sonuç seçimi ve isteğe
bağlı not kaydı iki adımlıdır. Arama izni seçeneği, buna bağlı engelleme ve
“Bir daha aranmasın” eylemi ürün kapsamından çıkarılmıştır.

Kişinin yaptığı arama `direction=inbound, purpose=marketing` bağlamıyla ayrı
kaydedilir. Bu yol fırsat detayında “Gelen arama” adıyla açıkça ayrılır. Dosya
sürecindeki görüşmeler pazarlama değil hizmet iletişimidir ve
`purpose=service` olarak kaydedilir. Çağrı bağlamı veritabanı CHECK kısıtıyla
yalnız telefon görüşmelerinde ve geçerli değerlerle tutulur.

Takip panosunun sütunları etkin `lead` statülerinden üretilir. Kartlar doğrudan
geçiş bulunan statüler arasında sürüklenebilir ve tıklanınca hızlı detay açar. Hedef statünün
form gereksinimleri `statuses.required_fields` JSONB alanında tutulur.
`callback`, `lost` ve dönüşüm alanları bu yapılandırmadan okunur; ayrıca aynı
kurallar PostgreSQL trigger'ıyla doğrudan yazımlarda korunur.

`statuses.converts_to_deal=true` olan hedefe geçişte tek transaction içinde:

1. fırsat `StatusMachine` ile terminal hedefe taşınır;
2. etkin ve `is_initial=true` dosya statüsünde yeni dosya oluşturulur;
3. seçilen program sürümünün checklist'i `ChecklistGenerator` ile üretilir;
4. fırsat `converted_deal_id` benzersiz bağıyla dosyaya bağlanır;
5. dönüşüm aktivitesi, outbox olayı ve `deal.assign` yetkililerine bildirim
   yazılır.

Checklist üretimi dahil herhangi bir adım hata verirse dış transaction bütün
zinciri geri alır. Benzersiz `converted_deal_id`, uygulama kontrolüne ek olarak
ikinci dönüşümü veritabanında da engeller.

## Sonuçlar

- Geciken aramalar ilk sırada, yoğun listede taranabilir ve dar ekranda kullanılabilir durumdadır.
- Pazarlama kullanıcısı yalnız kendi fırsatlarını görür; doğrudan URL de aynı
  kapsam kontrolünden geçer.
- Görüşmeler fırsat/dosyadan ayrı, her temas için yeni satırdır ve fırsat
  statüsünü kendiliğinden değiştirmez.
- Giden, gelen ve hizmet görüşmeleri ayrı bağlam işaretleriyle denetlenebilir
  kalır; görüşme kaydı arama iznine bağlı değildir.
- Statü davranışı veritabanı yapılandırması olarak kalır.
- Dönüşüm yarım dosya, yarım checklist veya çift dosya üretemez.

## Alternatifler

Yoğun listenin dar ekranda kolonları taşırması reddedildi; kolonlar anlamlı
satır bloklarına dönüşür. Fırsat dönüşümünü Filament sayfasında ardışık model
yazımlarıyla yapmak reddedildi; atomiklik ve gelecekteki mobil/portal tüketimi
bozulurdu.
