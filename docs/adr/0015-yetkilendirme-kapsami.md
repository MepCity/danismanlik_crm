# ADR-0015: Yetkilendirme kapsamı ve acil erişim

- **Durum:** Kısmen değiştirildi — [ADR-0032](0032-operasyon-girisleri-erisim-ve-ileti-onayi.md)
- **Tarih:** 2026-08-11
- **İş paketi:** WP-12

## Bağlam

İşlem izni tek başına hangi satırların görülebileceğini belirlemiyor. Özellikle
liste sorgusunun policy kontrolünden önce bütün iş verisini döndürmesi, doğrudan
nesne erişimi kadar ciddi bir yetkisiz erişim oluşturuyor. Sistem yöneticisinin
teknik yapılandırma yetkisi de müşteri ve belge verisine kalıcı erişim anlamına
gelmemeli.

## Karar

Etkin kapsam `users.data_scope` doluysa bu değer, değilse etkin rollerin
`default_scope` değerleri üzerinden çözülür. Birden fazla rolde **en dar kapsam**
kazanır: `none < own < team < all`. Genişletme gerekiyorsa kullanıcı üzerindeki
açık ezme kullanılır. Bu seçim en az yetki ilkesini korur ve ikinci rol atamanın
fark edilmeden veri görünürlüğünü büyütmesini engeller.

Liste sorguları sessiz global scope yerine açık `ScopedQuery` servisinden geçer.
Global scope seçilmedi; kuyruk işleri, denetim, seeder ve bakım işlemlerinde
oturum kullanıcısına bağlı eksik veri üretme riski taşır. Ham Eloquent sorgusu
sistem işleri için kapsamlanmamış kalır; kullanıcıya sunulan her liste
`ScopedQuery::apply()` çağırır. Test, ham sorgunun bütün satırları; aynı sorgunun
`ScopedQuery` yolundan ise yalnız izinli satırları döndürdüğünü sabitler.

## Sahiplik sözleşmesi

| Varlık | `own` sahipliği | `team` genişlemesi |
|---|---|---|
| Company | ilişkili `leads.owner_user_id` veya `deals.opened_by_user_id / pm_user_id` | aynı kolonlarda ekip kullanıcıları |
| Contact | bağlı Company sahipliği | bağlı Company ekip sahipliği |
| Lead | `owner_user_id` | ekip kullanıcılarının `owner_user_id` değeri |
| Interaction | `user_id` | ekip kullanıcılarının `user_id` değeri |
| Deal | `opened_by_user_id` veya `pm_user_id` | iki kolonda ekip kullanıcıları |
| DealDocument | bağlı Deal sahipliği | bağlı Deal ekip sahipliği |
| File | bağlı DealDocument → Deal sahipliği | bağlı Deal ekip sahipliği |
| Comment | bağlı Lead, Deal veya DealDocument sahipliği | bağlı öznenin ekip sahipliği |
| Task | `assigned_to`, `created_by` veya bağlı özne sahipliği | aynı alanlarda ekip kullanıcıları |

Program, ProgramVersion ve DocTemplate müşteri iş verisi değil teknik
yapılandırmadır. Bunlar veri kapsamından bağımsız `program.view` ve
`program.manage` izinleriyle korunur. Böylece sistem yöneticisi yapılandırmayı
yönetebilir, fakat `none` kapsamıyla iş verisi listeleri boş kalır.

Policy kayıtları açıkça kaydedilir; `Gate::before` süper kullanıcı kısa yolu
yoktur. `viewAny`, `view`, `create`, `update` ve pasifleştirme eşdeğeri
`deactivate` izinle birlikte kapsamı denetler. Fiziksel `delete` reddedilir.

Break-glass yalnız normal etkin kapsamı `none` olan kullanıcıya Şirket
Yetkilisi tarafından verilir. Gerekçe ve bitiş zamanı zorunludur; üst sınır
**60 dakika**dır. Etkin grant geçici iş-verisi okuma/indirme izinlerini ve `all`
kapsamını açar. İptal veya süre aşımı sorgu anında erişimi kapatır. Verilme,
kullanım, iptal ve süre aşımı ayrı `access.break_glass_*` olayları olarak
salt-ekleme yetki geçmişine yazılır; verilme anında bütün etkin Şirket
Yetkililerine uygulama içi bildirim oluşturulur.

> **Değişiklik (21.08.2026):** Bu break-glass kararı müşteri kararıyla kaldırıldı. `ScopedQuery`, policy ve veri kapsamı sözleşmesi geçerlidir; yeni gerekçeli erişim modeli ADR-0032’dedir.

## Sonuçlar ve kapsam dışı

- PostgreSQL RLS eklenmedi; K-07 korunuyor.
- Filament, yeni route ve müşteri portalı yetkileri eklenmedi.
- 2FA/TOTP bu pakette uygulanmadı; PLAN.md §7.3 uyarınca WP-14/15 arayüz
  paketlerinde Şirket Yetkilisi ve Sistem Yöneticisi için ele alınacak.
