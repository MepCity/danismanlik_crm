# ADR-0008: Program, dosya ve belge şeması

- Durum: Kabul edildi
- Tarih: 2026-08-11

## Bağlam

Program çağrılarının evrak kuralları zamanla değişirken mevcut dosyaların hukuki
bağlamı korunmalıdır. Aynı firma ve çağrı ilişkisinin tekilliği ile birden çok
hedefe bağlanan kayıtların referans bütünlüğü de şema kurulmadan karara
bağlanmalıdır. Domain modelleri ilişkileri ifade ederken ADR-0003 modül sınırını
delmemelidir.

## Karar

### Sözleşmesel program sürümü

Her çağrı `program_versions` satırıdır; evrak şablonları bu satıra bağlanır.
Dosya açıldığında şablon hem kaynak referansıyla hem de okunabilir alanların
anlık görüntüsüyle `deal_documents` tablosuna kopyalanır. Böylece 2027 çağrısının
evrak listesi değiştiğinde 2026 çağrısına açılmış dosyanın hukuki bağlamı
değişmez. Dosyaya özel ek belgelerde şablon kaynağı NULL olabilir, çağrı kaynağı
ise her zaman korunur.

### Aynı firma ve çağrıda yeniden başvuru

`deals(company_id, program_version_id)` tekil değildir; yalnız bileşik indeks
taşır. Normal akışta bir başvuru beklenmesine rağmen ret, geri çekme veya kurumun
yeniden başvuru istemesi aynı çağrıda ikinci bir dosya doğurabilir. Her dosyanın
tekil `reference_no` değeri operasyonel kimliği sağlar. İkinci başvuruyu şema
seviyesinde yasaklamak kullanıcıyı eski dosyayı üzerine yazmaya iter ve geçmişi
bozar.

### Çok hedefli kayıtlar

Az ve kapalı hedef kümesi için `subject_type + subject_id` yerine her hedefe ayrı
nullable FK ve `num_nonnulls(...) = 1` CHECK seçilmiştir. WP-05 `interactions`
tablosu `lead_id | deal_id` biçimine geçirilmiştir. WP-07'de `comments`, `tasks`
ve `activities` aynı deseni `lead_id | deal_id | deal_document_id` olarak
uygulayacaktır. Kolon sayısı artar; karşılığında var olmayan veya yanlış türde
özne kimlikleri DB seviyesinde engellenir ve `RESTRICT` sözleşmesi korunur.

### Modüller arası Eloquent ilişkileri

Domain modelleri başka bir modülün `Models` sınıfını doğrudan içe aktarmaz.
Çapraz ilişkiler `config/domain-models.php` içindeki uygulama bileşim kökünden
`DomainModelRegistry` ile class-string olarak çözülür; aynı modül içindeki
ilişkiler doğrudan ve tipli kalır. Böylece ilişkiler Eloquent'ta kullanılabilir,
uygulama bileşimi tek yerde görünür ve PHPStan sınır kuralı gevşetilmez.

## Sonuçlar

Program sürümü ve belge anlık görüntüleri geçmişi yeniden üretir. Yeniden
başvurular eski dosyayı değiştirmez. Çok hedefli tablolarda yeni hedef eklemek
migration gerektirir; bu, bu dar kapsamlı sistemde serbest ve doğrulanamayan
polymorphic kimliklere tercih edilen bilinçli maliyettir.
