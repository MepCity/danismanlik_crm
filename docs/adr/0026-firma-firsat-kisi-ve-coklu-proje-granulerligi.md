# ADR-0026: Firma, fırsat, kişi ve çoklu proje granülerliği

- Durum: Kabul edildi
- Tarih: 12.08.2026

## Bağlam

Pazarlamacının ilk aramadan sonra firma, görüştüğü kişi, program, görüşme sonucu, not ve takip işini nerede kaydedeceği belirsizdi. Firma kaydına tek bir statü vermek, aynı firmayla farklı tarihlerde veya eş zamanlı olarak birden çok program için çalışıldığında süreçleri birbirine karıştıracaktı. Görüşmelerin yalnız fırsat/dosyaya bağlı olması da gerçekte hangi kişiyle konuşulduğunu denetlenebilir biçimde göstermiyordu.

## Karar

1. `companies` tekil ana kayıttır; operasyon statüsü taşımaz.
2. Her program ilgisi ayrı `lead`, alınan her iş ayrı `deal` olur. Firma + program birleşimine tekillik uygulanmaz; aynı firma aynı program için yeniden proje açabilir.
3. Fırsat, görüşülen ana kişiyi `primary_contact_id` ile; her görüşme gerçekten görüşülen kişiyi `contact_id` ile taşır. Veritabanı tetikleyicisi kişinin ilgili fırsat/dosyayla aynı firmada olmasını zorunlu kılar.
4. Kişinin şirket içi unvanı ile satın alma kararındaki rolü ayrı tutulur.
5. Firma geneli yorum, görev ve okunabilir aktivite için `company` kontrollü işbirliği öznesidir. Programa özel not fırsatta, proje özel not dosyada kalır.
6. İlk kayıt tek atomik işlemde firma bulma/oluşturma, kişi bulma/oluşturma, fırsat, görüşme, DB-tabanlı statü geçişi, isteğe bağlı firma notu ve takip görevi üretir.
7. Yönetici atama ekranında işin alınma zamanı, pazarlamacı, görüşülen kişi, program ve satış görüşmesi bağlamını görür; atamadan sonra erişim `own | team | all` kapsamına göre proje yöneticisine açılır.

## Sonuçlar

- Firma geçmişi tek yerde okunur, fakat farklı fırsat ve projelerin statü/evrak/yorumları karışmaz.
- Aynı firmadaki iki proje ayrı referans, proje yöneticisi, checklist, belge sürümü ve zaman tüneli taşır.
- Aktör bilgisi uygulama aktivitesinde ve DB denetim defterinde korunur.
- Serbest polymorphic ilişki yerine gerçek nullable FK kolonları ve “tam olarak bir özne” CHECK kısıtı kullanılmaya devam eder.
