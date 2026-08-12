# ADR-0025: Kabul testi ve canlıya çıkış kapısı

- Durum: Kabul edildi
- Tarih: 12.08.2026

## Bağlam

Önceki paketler kendi servis, ekran ve altyapı dilimlerini test etti. Pazarlama aramasından kurum kararına uzanan zincir tek çalıştırmada doğrulanmamış; müşterinin uygulayıp imzalayacağı betik ve donanım/mevzuat dahil bağlayıcı yayın kapısı yazılmamıştı.

## Karar

1. Tek Pest testi, görüşmeden onay sonucuna kadar gerçek domain servisleriyle ana akışı yürütür. Eksik evrakta reddedilen ve tamamlandıktan sonra geçen statü adımı aynı zincirdedir. Doğrudan DB durum yazmak kabul akışı sayılmaz.
2. Dört rolün liste kapsamı, kayıt yetkisi ve doğrudan adres erişimi aynı iş grafiğinde otomatik test edilir. Policy ve scoped sorgu esastır.
3. Müşteri kabulü, her adımda yapılacak iş, beklenen sonuç, ✅/❌ ve imza alanı bulunan `docs/kabul-testi.md` ile yürütülür.
4. `docs/canliya-cikis-kontrol-listesi.md` yayın kapısıdır. K-10 donanım, tönel, restore, ClamAV, SMTP, sırlar, izleme, KVKK/İYS, gerçek program, demo temizliği ve imzalı kabul tamamlanmadan canlı veri alınmaz.
5. Demo temizliği yalnız açık demo işaretleri ve bağlı grafiği hedefleyen, onay metni isteyen komutla yapılır. Demo hesabı gerçek iş verisine bağlıysa durur; referans statü/geçiş/rol/izin/program/şablon silinmez.

## Sonuçlar

- Entegrasyon kopukluğu dilim testleri yeşil olsa bile yayını durdurur.
- Müşteri kabulü tekrarlanabilir ve imzalanabilir; sözlü “çalışıyor” kabul kanıtı değildir.
- Operasyonel ve hukuki dış bağımlılıklar uygulama testlerinden ayrı, aynı yayın kararının zorunlu girdisidir.
- Demo temizliği normal iş kayıtlarının silinmeme kuralını gevşetmez; yalnız kurgusal kurulum verisine uygulanır.
