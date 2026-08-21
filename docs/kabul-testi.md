# Bizlife CRM müşteri kabul betiği

Bu betik canlıya çıkmadan önce sistemi günlük işinde kullanacak kişilerce uygulanır. Teknik bilgi gerekmez. Her senaryoda belirtilen rolle oturum açın; sonucu gördüğünüzde kutuyu işaretleyin. Gerçek müşteri verisi yerine eğitim için oluşturulmuş kurgusal bir firma kullanın.

## Hazırlık

- En az bir Pazarlama, Proje Yöneticisi, Şirket Yetkilisi ve Sistem Yöneticisi hesabı hazır olmalı.
- Test programında en az iki zorunlu ve firma ili veya tutarla devreye giren bir koşullu evrak bulunmalı.
- Test firmasında e-posta izni ve ulaşılabilir bir test e-posta adresi olmalı.
- Bir PDF'nin ilk ve düzeltilmiş iki kurgusal sürümü hazır olmalı.

## 1. Pazarlama — arama ve fırsat

| Ne yapılacak | Ne görülmeli | ✅/❌ |
|---|---|---|
| Pazarlama hesabıyla “Bugün aranacaklar” ekranını açın. | Yalnız bu kullanıcının bugün arayacağı ve geciken firmalar görünür. | ☐ ✅ ☐ ❌ |
| Test firmasında “Ara” deyip sonuç ve kısa not girin. | Görüşme; saat, kullanıcı ve sonuçla geçmişte görünür. | ☐ ✅ ☐ ❌ |
| Fırsatı “İlgileniyor” yapın. | Kart doğru sütuna geçer; eski ve yeni statü geçmişte okunur. | ☐ ✅ ☐ ❌ |
| Ana fırsatı “Teklif gönderildi”, sonra “İş alındı” yapıp program sürümünü seçin. | Tek dosya otomatik açılır; “Atama bekliyor” durumunda, seçilen program ve evrak listesiyle görünür. | ☐ ✅ ☐ ❌ |

## 2. Şirket yetkilisi — atama

| Ne yapılacak | Ne görülmeli | ✅/❌ |
|---|---|---|
| Şirket Yetkilisi hesabıyla bekleyen atamaları açın. | Yeni dosya firma ve dosya numarasıyla listelenir. | ☐ ✅ ☐ ❌ |
| Dosyayı açın, etkin bir PM seçip “Ata ve süreci başlat” deyin. | Dosya “PM atandı” olur; kişi ekipte görünür ve kendisine bildirim gider. | ☐ ✅ ☐ ❌ |

## 3. Proje yöneticisi — evrak ve başvuru

| Ne yapılacak | Ne görülmeli | ✅/❌ |
|---|---|---|
| PM hesabıyla dosyayı “Belgeler toplanıyor” yapıp Belge listesine girin. | Programa ait evraklar zorunluluk ve durumlarıyla listelenir. | ☐ ✅ ☐ ❌ |
| “Eksik evrak listesini firmaya gönder” deyin. | E-posta kuyruğa alınır; satırlar “Talep edildi” olur, talep zamanı dolar ve e-postada eksik evrak adları vardır. | ☐ ✅ ☐ ❌ |
| Bir evraka ilk PDF'yi yükleyip incelemeye alın. | Evrak “Yüklendi”, sonra “İnceleniyor” olur; Sürüm 1 görünür. | ☐ ✅ ☐ ❌ |
| “Reddet” deyip gerekçeyi boş bırakın. | Sistem gerekçe ister ve kararı kaydetmez. | ☐ ✅ ☐ ❌ |
| “İmza sayfası eksik” gerekçesiyle reddedin. | Evrak “Eksik/hatalı” olur; gerekçe geçmişte okunur. | ☐ ✅ ☐ ❌ |
| Düzeltilmiş PDF'yi yükleyip inceleyin ve kabul edin. | Sürüm 1 ve 2 korunur; güncel durum “Kabul edildi” olur. | ☐ ✅ ☐ ❌ |
| Eksik evrak varken “Başvuru hazırlanıyor” geçişini deneyin. | Geçiş reddedilir, eksik evrak adları tek tek gösterilir ve dosya durumu değişmez. | ☐ ✅ ☐ ❌ |
| Firma ilini veya talep tutarını test koşulunu sağlayacak şekilde değiştirin. | Koşullu evrak otomatik eklenir; PM'e hangi evrağın eklendiğini söyleyen bildirim gelir. | ☐ ✅ ☐ ❌ |
| Tüm zorunlu ve koşullu evrakları yükleyip kabul edin. | Eksik sayısı sıfır ve zorunlu evrakların tamamlanma zamanı görünür. | ☐ ✅ ☐ ❌ |
| “Başvuru hazırlanıyor” geçişini yeniden deneyin. | Bu kez geçiş tamamlanır. | ☐ ✅ ☐ ❌ |
| Müşteri onayı, kuruma gönderim ve kurum değerlendirmesinden geçip sonucu “Onaylandı” kaydedin. | Başvuru no, gönderim/karar tarihleri ve onay sonucu görünür; dosya “Sonuçlandı” olur. | ☐ ✅ ☐ ❌ |

## 4. Geçmiş, yönetim ve yetki

| Ne yapılacak | Ne görülmeli | ✅/❌ |
|---|---|---|
| Her rol yetkili olduğu kayıtta “İşlem geçmişi”ni okusun. | Arama, statü, atama, talep, yükleme, ret gerekçesi, yeni sürüm ve kabul teknik kod olmadan; kim/ne zaman bilgisiyle anlaşılır. | ☐ ✅ ☐ ❌ |
| Pazarlama başka pazarlamacının fırsatını, PM başka ekibin dosyasını arasın. | Kapsam dışı kayıt listede ve aramada görünmez. | ☐ ✅ ☐ ❌ |
| Yetkisiz kullanıcı başka dosyanın adresini tarayıcıya yapıştırsın. | İçerik açılmaz; 403 / erişim yasak yanıtı gelir. | ☐ ✅ ☐ ❌ |
| Sistem Yöneticisi program tanımlayıp sürüm, evrak ve basit koşul eklesin. | Yapılandırma kaydolur; Sistem Yöneticisi müşteri dosya ve belgelerini varsayılan olarak göremez. | ☐ ✅ ☐ ❌ |
| Şirket Yetkilisi veya rapor yetkili PM Raporları açıp Excel indirsin. | Ekran ve Excel yalnız kullanıcının yetkili olduğu aynı veriyi içerir. | ☐ ✅ ☐ ❌ |

## Kabul imzası

Kabul kararı: ☐ Kabul · ☐ Red

Tarih: ____ / ____ / ______

Müşteri adı ve görevi: ______________________________________________

İmza: ______________________________________________

Notlar / reddedilen adımlar ve ekran görüntüsü referansları:

________________________________________________________________________

________________________________________________________________________
