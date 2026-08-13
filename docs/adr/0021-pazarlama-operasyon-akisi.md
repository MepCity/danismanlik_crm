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

“Bugün aranacaklar” ekranı yoğun, satır tabanlı çalışma düzenidir. `next_call_at` bugün
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

Giden pazarlama araması kaydı yalnız arayüzde değil, `RecordInteraction`
aksiyonunda da korunur. Karar kaynağı `contacts` üzerindeki hızlı sorgu özeti
değil, birincil kişinin arama+pazarlama amacı için görüşme zamanında etkin olan
son `communication_consents` satırıdır. Son durum `granted` değilse kayıt açık
bir doğrulama hatasıyla reddedilir. Kişi satırı kilitlendiği için eşzamanlı ret
ve arama kaydı aynı izin durumu üzerinde sıralanır.

Engel mutlak değildir: kişinin kendisinin yaptığı arama ayrı
`direction=inbound, purpose=marketing` bağlamıyla kaydedilebilir ve ret bunu
engellemez. Bu yol fırsat detayında “Gelen arama” adıyla açıkça ayrılır. Dosya
sürecindeki görüşmeler pazarlama değil hizmet iletişimidir ve
`purpose=service` olarak kaydedilir. Geçmiş bir giden arama, aramanın gerçekleştiği
anda defterde izin varsa sonradan girilebilir; böylece daha sonra verilen ret
geçmişte hukuka uygun yapılmış temasın kaydını bozmaz. Çağrı bağlamı veritabanı
CHECK kısıtıyla yalnız telefon görüşmelerinde ve geçerli değerlerle tutulur.

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
- Retten sonraki giden pazarlama araması servis katmanında reddedilir; gelen
  arama ve hizmet görüşmesi ayrı bağlam işaretleriyle denetlenebilir kalır.
- Statü davranışı veritabanı yapılandırması olarak kalır.
- Dönüşüm yarım dosya, yarım checklist veya çift dosya üretemez.

## Alternatifler

Yoğun listenin dar ekranda kolonları taşırması reddedildi; kolonlar anlamlı
satır bloklarına dönüşür. Fırsat dönüşümünü Filament sayfasında ardışık model
yazımlarıyla yapmak reddedildi; atomiklik ve gelecekteki mobil/portal tüketimi
bozulurdu. İzin durumunu yalnız renk veya gizlenmiş aksiyonla anlatmak
reddedildi; ret sebebi ve zamanı metin olarak görünmelidir.
