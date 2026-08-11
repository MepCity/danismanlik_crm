# ADR-0021: Pazarlama operasyon akışı

## Durum

Kabul edildi

## Bağlam

Pazarlama ekibi sahada dizüstü açmadan günlük takip aramalarını tamamlamalı,
her aramayı fırsattan ayrı kaydetmeli ve arama öncesinde kişinin KVKK/İYS
durumunu açıkça görmelidir. “İş alındı” geçişi fırsat sürecinin bittiği ve
programa bağlı dosya sürecinin başladığı tek kritik sınırdır.

Statüler, geçiş davranışları ve zorunlu evraklar koda gömülemez. Ayrıca izin
defteri salt-ekleme kalmalı; `contacts` üzerindeki alanlar yalnız güncel sorgu
özetidir.

## Karar

“Bugün aranacaklar” ekranı mobil öncelikli kart düzenidir. `next_call_at` bugün
veya geçmişte olan, kullanıcının kapsamındaki fırsatlar alınır; en eski tarih
önce gelir. Kart firma, kişi, telefon, son görüşme sonucu, arama sırası ve izin
durumunu birlikte gösterir. Telefon `tel:` bağlantısıdır. `do_not_call=true`
olduğunda bağlantı üretilmez; aksiyonun neden engellendiği kırmızı durum
şeridinde yazılır. Hazır sonuç seçimi ve isteğe bağlı not kaydı iki adımlıdır.

İzin görünümü üç katmandan oluşur:

- güncel arama izni ve `do_not_call` özeti;
- iletişim bilgisinin zorunlu `contacts.data_source` alanı;
- salt-ekleme `communication_consents` defterindeki aydınlatma ve son ret
  zamanı.

“Bir daha aranmasın” işlemi mevcut izin satırını değiştirmez. Yeni bir
`withdrawn` satırı ekler ve aynı transaction içinde kişi özetini aramaya kapatır.

Fırsat panosunun sütunları etkin `lead` statülerinden üretilir. Hedef statünün
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

- Geciken aramalar ilk sırada ve dar ekranda kullanılabilir durumdadır.
- Pazarlama kullanıcısı yalnız kendi fırsatlarını görür; doğrudan URL de aynı
  kapsam kontrolünden geçer.
- Görüşmeler fırsat/dosyadan ayrı, her temas için yeni satırdır ve fırsat
  statüsünü kendiliğinden değiştirmez.
- Statü davranışı veritabanı yapılandırması olarak kalır.
- Dönüşüm yarım dosya, yarım checklist veya çift dosya üretemez.

## Alternatifler

Masaüstü tabloyu mobilde yatay kaydırmak reddedildi; sahada tek elle kullanım
için kart düzeni gerekir. Fırsat dönüşümünü Filament sayfasında ardışık model
yazımlarıyla yapmak reddedildi; atomiklik ve gelecekteki mobil/portal tüketimi
bozulurdu. İzin durumunu yalnız renk veya gizlenmiş aksiyonla anlatmak
reddedildi; ret sebebi ve zamanı metin olarak görünmelidir.
