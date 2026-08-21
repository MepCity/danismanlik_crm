# ADR-0032: Operasyon girişleri, erişim ve ileti onayı

- Durum: Kabul edildi
- Tarih: 21.08.2026
- Kaynak: Müşteri kararı

## Bağlam

Ayrı potansiyel müşteri, kullanıcı, rol ve acil erişim ekranları dar operasyon ürününde aynı işe ulaşmak için gereksiz giriş noktaları oluşturuyordu. Sistem Yöneticisinin kendi sayfa ve veri kapsamını değiştirebildiği modelde break-glass önleyici bir güvenlik sınırı sağlamıyordu. Hizmetler ise bağlanmış rehber, evrak listesi ve çağrı dönemi tamamlanmadan aktif hale gelebiliyordu.

Filtreli toplu e-posta güncel izni denetliyor ancak metinleri tekrar tekrar serbest biçimde alıyor ve alıcıya kendi iznini geri çekebileceği bir yol sunmuyordu.

## Karar

1. Firma unvan ve sektörle doğrudan oluşturulur. Fırsat firma ekranından isteğe bağlı açılır; müşteri dosyası fırsat olmadan da mevcut `StartCustomerFlow` zinciriyle oluşturulabilir. Her iki giriş aynı checklist, ilk statü ve atama bildirimi sözleşmesini kullanır.
2. Hizmet taslak başlar. Yayın için çağrı dönemi, bağlı etkin iş akışı ve en az bir etkin evrak şablonu zorunludur. Hizmet yorumları kalıcı `program` öznesine bağlanır; `comments` tablosundaki ayrı nullable FK ve tam-olarak-bir CHECK sözleşmesi korunur.
3. Kullanıcı, rol önayarı, doğrudan sayfa izinleri ve `own/team/all` kapsamı tek Erişim yönetimi ekranında düzenlenir. Roller yalnız hızlı önayardır; uygulama katmanı Spatie permission, policy ve `ScopedQuery` olmaya devam eder.
4. Sistem Yöneticisi kendine izin ve kapsam verebilir. Break-glass kaynakları, servisi ve tablosu kaldırılır. Kontrol önleyici kendine-yetki engeline değil; zorunlu gerekçeli `role_permission_history`, okunabilir aktivite ve tetikleyici denetim izine dayanır.
5. Toplu e-posta etkin şablon seçer. İzin verilen değişken kataloğu firma adı/unvanı, yetkili adı, sektör ve ildir; bilinmeyen değişken kaydı reddeder. Modal, seçilmiş firmalardaki gerçek bir kişiyle doldurulmuş önizleme gösterir.
6. Her pazarlama e-postasına kişiye özel, imzalı ve süreli abonelikten çıkma bağlantısı eklenir. Giriş gerektirmeyen uç nokta yeni `email / marketing / withdrawn` izin satırı ekler; önceki satırlar değişmez. Denormalize özet kapanır ve mevcut gönderim denetimi kişiyi sonraki gönderimden çıkarır.

## Sonuçlar

- Firma kaydı fırsat hunisini zorunlu kılmaz; bugün aranacaklar ve takip panosu isteyen ekip için korunur.
- Eksik yapılandırılmış hizmet dosya açılabilir hale gelemez ve eksikler tek seferde gösterilir.
- Teknik yönetim ile iş verisi izinleri ayrı kalır; yetki yükseltme mümkündür ancak gerekçesi ve aktörü silinmez biçimde izlenir.
- E-posta metni yönetilebilir ve önizlenebilir olur; izin geri çekme alıcının doğrudan kullanabildiği yazılımsal kontrole dönüşür.
- Kampanya analitiği, gönderim sıklığı otomasyonu ve yeni posta altyapısı kapsam dışıdır.
