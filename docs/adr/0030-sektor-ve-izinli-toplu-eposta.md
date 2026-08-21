# ADR-0030: Kontrollü sektör ve izinli toplu e-posta

- Durum: Kabul edildi
- Tarih: 21.08.2026

## Bağlam

Firma rehberi müşteri olmayan kayıtları da içerir. Pazarlama ekibinin yeni bir destek duyurusunda doğru firmaları bulabilmesi için “gıda”, “metal” veya “bilişim” gibi tutarlı kaba segmentler gerekir. Serbest metin yazımı sessiz filtre hatası üretir. NACE kodu ise bu kullanım için fazla ince tanelidir ve ilk telefon görüşmesinde bilinmeyebilir.

Filtrelenmiş firmalara e-posta gönderimi, kanal ve amaç bazlı güncel izin doğrulanmadan yapılamaz. Mevcut sistemde salt-ekleme `communication_consents` defteri, hızlı sorgu özeti ve kuyruklu dış alıcı bildirimi zaten vardır.

## Karar

1. Sektör, ayrı bir yönetim modülü gerektirmeyen sabit kaba liste olduğu için `companies.industry` kısıtlı kolonunda tutulur. Uygulama doğrulamasıyla aynı kod kümesi PostgreSQL `CHECK` kısıtıyla da korunur.
2. NACE isteğe bağlı ayrı alandır; kaba sektör NACE’den otomatik türetilmez. Böylece pazarlama segmenti mevzuat sınıflamasındaki ayrıntı veya değişikliklere bağlanmaz.
3. Toplu e-posta yalnız seçilmiş ve kullanıcının kapsamında bulunan firmalarda çalışır. Her aktif kişinin e-posta adresi, `contacts.consent_email` özeti ve defterdeki yürürlükte olan son `email + marketing` kaydı birlikte doğrulanır.
4. İzni olmayan, adresi eksik ve mükerrer alıcılar kuyruğa alınmaz. Sonuç sayıları kullanıcıya açıkça gösterilir.
5. Gönderimler yeni bir kampanya veya posta altyapısı kurmaz; mevcut `notifications` tablosu ve `SendNotificationEmail` işi kullanılır. Aktör, etkin filtreler ve alıcı sayıları okunabilir firma etkinliğine yazılır; veritabanı tetikleyicili denetim katmanı ayrıca çalışmaya devam eder.
6. Kişiselleştirme yalnız `{{firma_adi}}` ve `{{yetkili_adi}}` alanlarıyla sınırlandırılır.

## Sonuçlar

- Sektör filtresi yazım farklarından etkilenmez; NACE verisi bilinmediğinde hızlı firma kaydı engellenmez.
- Pazarlama e-postası yalnız e-posta iznine bakar ve izinsiz alıcıyı sessizce işlemez.
- Gönderim girişimi firma bazında izlenebilir; aynı kuyruk teslim/hata ve yeniden deneme davranışını korur.
- Kampanya analitiği, otomatik abonelikten çıkma bağlantısı ve frekans sınırı bu kararın kapsamına alınmamıştır.
