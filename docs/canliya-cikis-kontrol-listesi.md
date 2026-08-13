# Canlıya çıkış kontrol listesi

Bu belge yayın kapısıdır. Her madde kanıtı ve sorumlusu yazılarak işaretlenmeden sistem canlı müşteri verisiyle kullanılmaz. Sözlü teyit tamamlanmış sayılmaz.

## Donanım ve fiziksel ortam — K-10

- [ ] UPS kurulu; elektrik kesintisi ve kontrollü kapanma denendi. Kanıt/tarih: ______ Sorumlu: ______
- [ ] Uygulama adanmış, günlük iş için kullanılmayan makinede. Kanıt: ______
- [ ] İki disk mirror var veya saatlik ofis dışı yedekle eşdeğer risk kabulü yazıldı. Tercih/kanıt: ______
- [ ] Makine kilitli oda/dolapta; fiziksel erişim listesi yazılı. Liste: ______
- [ ] Makinenin hangi ofiste olduğu ve uzaktan bakım yetkisi belirlendi; gerekiyorsa veri işleyen sözleşmesi imzalandı. Kanıt: ______
- [ ] Ofis internet kesintisi riski kabul edildi veya yedek hat/failover kuruldu. Karar: ______

## Erişim, uygulama ve sırlar

- [ ] Tünel hesabı etkin ve çalışıyor; modemde uygulama için port yönlendirmesi yok. Dış ağ test tarihi: ______
- [ ] TLS/alan adı doğrulandı; sertifika uyarısı yok. Kanıt: ______
- [ ] `APP_ENV=production` ve `APP_DEBUG=false` çalışan container içinden sorgulandı. Kanıt: ______
- [ ] Uygulama, DB, Redis, S3, tönel ve yedek sırları benzersiz/güçlü; depoda ve PR geçmişinde yok. Tarama: ______
- [ ] Gerçek SMTP bağlı; uygulama dışı posta kutusuna test e-postası ulaştı. Alıcı/tarih: ______
- [ ] Kullanıcılar kendi adlarına açıldı, roller ikinci kişi tarafından kontrol edildi; ortak hesap yok. Kontrol eden: ______

## Belge, zararlı yazılım ve yedek

- [ ] ClamAV canlı; EICAR test dosyası yakalandı. Test tarihi/kanıt: ______
- [ ] Nesne deposu uygulama sunucusundan ayrı veya ikinci hedefe replike; herkese açık nesne yok. Kanıt: ______
- [ ] Günlük şifreli ofis dışı yedek ve WAL arşivi çalışıyor. Son başarılı yedek: ______
- [ ] Yedek anahtarı yedekten ayrı yerde ve en az iki yetkilice erişilebilir. Yetkililer: ______
- [ ] Son geri dönüş tatbikatı başarılı. **Son tatbikat tarihi:** ______ RPO: ______ RTO: ______ Kanıt: ______

## İzleme ve operasyon

- [ ] Uygulama, kuyruk, disk, PostgreSQL, yedek, sertifika ve tönel uyarıları gerçek kanala düştü. Kanal: ______
- [ ] Uyarıları kimin göreceği, ilk müdahale ve yedek kişi yazılı. Birincil/yedek: ______
- [ ] Üretim runbooku, geri dönüş ve olay müdahalesi sorumlularca okundu. Tarih/imza: ______
- [ ] `make prod-preflight`, `make lint`, `make analyse`, `make test` son yayın commit'inde başarılı. Commit/CI: ______

## KVKK ve İYS

- [ ] VERBİS yükümlülüğü ve kayıt durumu hukuk/KVKK danışmanınca incelendi. Sonuç: ______
- [ ] Aydınlatma metni veri kaynakları/amaçlarla uyumlu ve kullanıcılara sunuldu. Sürüm/tarih: ______
- [ ] Saklama/imha politikasında veri türü bazında süre, yöntem ve sorumlu yazılı. Belge: ______
- [ ] Alt işleyen envanteri e-posta, hata izleme, SSO, tönel, nesne/yedek dahil güncel; yurt dışı aktarım güvenceleri kontrol edildi. Onay: ______
- [ ] Pazarlama araması, SMS/e-posta, ret ve “bir daha aranmasın” senaryoları hukuk/KVKK danışmanınca İYS/KVKK açısından yazılı onaylandı. Onay/tarih: ______

## Veri, içerik ve kabul

- [ ] [Açık işler](acik-isler.md) belgesindeki canlıya etki eden tüm kararlar kapandı. Onaylayan: ______
- [ ] Müşterinin gerçek programları, sürümleri, evrak listeleri ve koşulları müşteriyle girilip kontrol edildi. Programlar/onay: ______
- [ ] KOSGEB Yeşil Sanayi kaydının müşterice doğrulanmamış örnek olduğu teyit edildi; gerçek veri diye kullanılmıyor veya müşteri doğruladı. Karar: ______
- [ ] Veri göçü tamamlanıp sayı/alan kontrolü yapıldı veya göç olmadığı yazılı teyit edildi. Kanıt: ______
- [ ] `make purge-demo` çalıştı; demo firma, dosya, belge nesnesi ve Bizlife demo hesabı kalmadı. Tarih: ______
- [ ] Temizlik sonrası statü, geçiş, rol, izin, program ve evrak şablonu referansları korundu. Kontrol eden: ______
- [ ] [Müşteri kabul betiği](kabul-testi.md) eksiksiz yürütüldü ve imzalandı. Tarih/belge yeri: ______

## Yayın kararı

- [ ] Bütün maddeler tamamlandı; canlıya çıkış onaylandı.

Planlanan yayın tarihi/saati: ____________________

Müşteri karar sahibi — ad/imza: ____________________

Teknik karar sahibi — ad/imza: ____________________
