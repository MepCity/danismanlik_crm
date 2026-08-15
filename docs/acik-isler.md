# Canlıya çıkış öncesi açık işler

PLAN.md §18 ve K-10'da açık kalan müşteri kararlarının tek takip yeri burasıdır. Cevap, karar sahibi ve kanıt yazılmadan madde kapanmaz.

| Durum | Soru / karar | Neden önemli | Kim cevaplayacak | Cevaplanmazsa ne olur | Cevap / kanıt |
|---|---|---|---|---|---|
| ✅ Kapandı | Ekip hangi dili/yığı biliyor? | Takvim ve bakım riskini belirler. | Teknik ekip | Sürdürülebilirlik belirsiz kalır. | K-03: PHP 8.4+, Laravel 13.24, Filament 5.7. |
| ☐ Açık | Müşteri fiilen hangi programlarla çalışıyor; güncel evrak ve koşulları nedir? | Checklist sistemin temel iş değeridir. KOSGEB Yeşil Sanayi verisi internet rehberinden alınmış, müşteriyle doğrulanmamış örnektir. | Şirket Yetkilisi + PM | Yanlış/eksik evrak istenir; örnek veri gerçek diye kullanılamaz. | Programlar: ____ Sürüm/tarih: ____ Onay: ____ |
| ☐ Açık | Kaç kullanıcı, yılda kaç dosya? | Kapasite, depolama, yedek ve destek maliyetini belirler. | Şirket Yetkilisi + operasyon | 15 kullanıcı/500 dosya varsayımıyla çıkılır; kapasite taahhüdü verilemez. | Kullanıcı: ____ Dosya/yıl: ____ |
| ☐ Açık | Excel/eski CRM verisi var mı; ne taşınacak? | Eşleme, temizlik, mükerrer kontrolü ve kabul ayrı efordur. | Müşteri veri sahibi + teknik sorumlu | Sistem boş başlar; sonraki göç ayrı iş/fiyat olur. | Kaynak/satır: ____ Karar: ____ |
| ☐ Açık | Ücret sabit mi, başarı primi mi, yüzde mi; ara ödeme ve PM tutar yetkisi var mı? | Hakediş ihtiyacı ve hassas tutar yetkisini belirler. | Şirket Yetkilisi + mali sorumlu | Hakediş/tahsilat v1'de olmaz. “Yüzde” ise muhasebe modülü olmayan küçük takip ayrı kapsamlanır; PM tutarı varsayılan görmez. | Model: ____ PM yetkisi: ____ |
| ☐ Açık | E-posta Google Workspace, M365 veya başka nerede? | SMTP, SSO ve e-posta iliştirme tasarımını belirler. | Müşteri BT/e-posta yöneticisi | v1 e-posta+parola ve standart SMTP ile çıkar; SSO/iliştirme taahhüt edilmez. | Sağlayıcı: ____ Yetkili: ____ |
| ✅ Kapandı | Müşteri portalı v1'de mi, sonra mı? | Dış kullanıcı izolasyonu ve test yükü kapsamı yaklaşık %25 büyütür. | Şirket Yetkilisi + ürün sahibi | PLAN kararı uygulanır: portal v1 dışında kalır. | 15.08.2026 kullanıcı kararı: Sisteme müşteri girişi ve müşteri portalı olmayacak; yorumlar yalnız iç nottur. |
| ☐ Açık | Belge hacmi ne: ayda kaç, hangi kanal, kaçı tarama; dış AI'a gidebilir mi? | OCR getirisi, maliyeti, hata ve KVKK aktarım riskini belirler. | PM + KVKK + teknik sorumlu | OCR v1'e alınmaz; manuel sınıflandırma kullanılır. | Belge/ay: ____ Tarama: ____ AI kararı: ____ |
| ☐ Açık | Sunucu kimin ofisinde; uzaktan kim erişecek? | Veri işleyen ilişkisi, fiziksel güvenlik ve bakım yetkisine bağlıdır. | Şirket Yetkilisi + KVKK + teknik sorumlu | Canlıya çıkılmaz; sağlayıcı ofisindeyse veri işleyen sözleşmesi gerekir. | Konum: ____ Erişim: ____ Sözleşme: ____ |

## Kapanış

Tüm açık maddelerde karar ve kanıt var: ☐

Müşteri karar sahibi / tarih / imza: ______________________________________

Teknik karar sahibi / tarih / imza: __________________________________________
