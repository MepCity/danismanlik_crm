# ADR-0010: Denetim ve işbirliği şeması

## Durum

Kabul edildi — 11.08.2026

## Bağlam

PLAN.md §8 iki ayrı geçmiş ister: kullanıcıya okunabilir olaylar sunan
`activities` ve uygulama dışındaki SQL değişikliklerini de yakalayan DB tabanlı
`audit_log`. §8.2 aktör bağlamının transaction-local PostgreSQL ayarlarından
okunmasını, §8.5 yorum düzenlemelerinin önceki hâlinin korunmasını ve §13.1 iç
domain olaylarının outbox'a aynı transaction içinde yazılmasını zorunlu kılar.

## Kararlar

### Partition ve salt-ekleme

`audit_log.created_at` üzerinden aylık RANGE partition kullanılır. Migration
mevcut ay ile sonraki üç ayı oluşturur. `audit:ensure-partitions` komutu her gün
çalışır ve aynı pencereyi ileri taşır; böylece ay sınırından önce hedef partition
hazırdır. Ek bir `pg_partman` bağımlılığı bu ölçek için gerekli değildir.

Uygulama, migration ve geliştirme ortamında aynı PostgreSQL rolünü
kullanabildiğinden rol adına bağlı `GRANT/REVOKE` taşınabilir değildir. Bunun
yerine `audit_log` üzerinde `BEFORE UPDATE OR DELETE` trigger'ı her rol için
mutasyonu reddeder. Bu seçim tablo sahibinin yanlışlıkla yaptığı mutasyonu da
engeller ve test ortamında üretimle aynı davranışı verir.

### Hassas alanlar ve izlenen tablolar

Tek generic `write_audit_log()` fonksiyonu kullanılır. Her trigger açık bir JSON
hariç tutma listesi verir; argümansız trigger kurulamaz. Listeler şöyledir:

| Tablo | Hariç tutulan alanlar | Gerekçe |
|---|---|---|
| `users` | `password`, `remember_token`, `api_token`, `two_factor_secret`, `two_factor_recovery_codes`, `signed_url_secret`, `e_signature_password` | Kimlik doğrulama, API, 2FA, imzalı URL ve e-imza sırları |
| `contacts` | `[]` | Kritik kişisel veri değişiklikleri; bu tabloda kimlik doğrulama sırrı yok |
| `leads` | `[]` | Fırsat sahipliği ve süreci |
| `deals` | `[]` | Merkez operasyon dosyası |
| `deal_documents` | `[]` | Evrak gereksinimi ve durumu |
| `files` | `[]` | Belge sürümü ve silinme işareti; `storage_key` opaque kimliktir, imzalama sırrı değildir |
| `comments` | `[]` | Düzenleme öncesi içeriği §8.5 gereği korumak |

`tasks` ve `notifications` ilk izleme kümesine alınmaz. Görevler normal iş verisi
olarak düzenlenebilir; bildirim teslimat alanları ise yüksek hacimli teknik durum
üretir. Gereksinim oluşursa aynı generic fonksiyonla ayrıca bağlanabilirler.

UPDATE kayıtlarında tüm satır değil yalnız değişen, hassas alanlardan arındırılmış
anahtarlar `old_data` ve `new_data` içine yazılır. INSERT ve DELETE kendi güvenli
satır görüntüsünü taşır. Aktör bağlamı yoksa `actor_id=NULL` ve `source=system`
bilinçli olarak kaydedilir.

### Uygulama modelleri

`Activity`, `Comment`, `Task` ve `Notification` işbirliği modülünde;
`OutboxMessage` destek/outbox katmanında yer alır. `audit_log` için Eloquent model
yazılmaz: tablo DB trigger'ının sahip olduğu salt-ekleme kayıt defteridir. İleride
okuma ihtiyacı doğarsa mutasyon metotları kapalı ayrı bir salt-okuma modeli
eklenebilir.

`activities.payload` ham diff taşımaz. Olay kodu, parametreleri ve olay anındaki
kullanıcı etiketleri kaydedilir; bu sözleşme DB comment'iyle de sabitlenir.

## Sonuçlar

- Ay partition'ı bulunmayan tarihe yazma bilinçli olarak hata verir; günlük komut
  bu operasyonel koşulu önceden sağlar.
- Audit kayıtları uygulama kodundan değiştirilemez ve hassas sırlar geçmişe
  kopyalanmaz.
- Dış webhook teslimatı ve `ProcessOutbox` işleme gövdesi bu kararın kapsamı
  dışındadır.
