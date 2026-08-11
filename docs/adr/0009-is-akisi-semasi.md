# ADR-0009: İş akışı şeması ve geçmiş korumaları

- Durum: Kabul edildi
- Tarih: 2026-08-11

## Bağlam

K-05 statü ve geçişlerin deploy gerektirmeden yönetilebilmesini, K-09 ise açık
dosyaları eski bir akışa kilitlemeden geçmişte yürürlükte olan kuralların yeniden
üretilebilmesini ister. Statüde geçirilen sürenin gerçek kaynağı olan
`status_history` satırı, çıkış anında kapanmak zorundadır. Genel “silinmez” ve
salt-ekleme kuralları bu üç veri türünde farklı veritabanı korumaları gerektirir.

ADR-0006 kullanıcı etiketlerini `lang/tr` kaynaklarına yerleştirir. Yönetici
tarafından çalışma anında tanımlanan statü etiketi bu kuralın dar istisnasıdır:
etiket çeviri dosyasında olursa yeni statü eklemek yine deploy gerektirir ve
K-05 uygulanamaz.

## Karar

### Statü ve geçiş kayıtları

`statuses` fırsat ve dosya akışlarını `type` ile ayırır; makine kodu tip içinde
tekil ve trigger ile değişmezdir. Etiket `statuses.label` alanında tutulur.
Bu alan uygulama metni değil, yönetici tarafından tanımlanan çalışma zamanı
verisidir. Renk, sıra, terminal ve aktif bayrakları kod karşılaştırmasına gerek
bırakmadan davranış ve sunum metadatasını taşır.

`statuses` ve `transitions` satırları silinemez; DB trigger'ı doğrudan SQL dahil
her `DELETE` girişimini reddeder. Kayıtlar `is_active=false` ile pasifleştirilir.
Geçişin izin ve JSONB koşul kancaları bu pakette yalnızca veridir; değerlendirme,
yan etki ve pasifleştirme öncesi yetim kontrolü WP-09 kapsamındadır.

### Revizyon ve statü geçmişi

`workflow_revisions`, statü ve geçiş kümesinin JSONB anlık görüntüsünü yürürlük
zamanı, değiştiren kullanıcı ve boş olmayan gerekçeyle saklar. UPDATE ve DELETE
trigger ile engellenir; düzeltme yeni bir revizyon satırıdır.

`status_history` append-only değildir. Bir statüden çıkılırken açık satırın
`exited_at` alanı güncellenir; bunu yasaklamak §5.6'daki süre ölçümünü bozar.
Satırın kanıt niteliğini korumak için yalnız DELETE engellenir. Her fırsat veya
dosya için partial unique index aynı anda tek açık satıra izin verir. Etiket
anlık görüntüsü, statünün güncel etiketi değişse bile geçmişi okunabilir tutar.

### Geçici statü bağları

`leads.status` ve `deals.status` kod kolonları kaldırılıp zorunlu, `RESTRICT`
davranışlı `status_id` yabancı anahtarlarına dönüştürülür. Tablolar WP-08 öncesi
boş olduğu için veri eşleme adımı yoktur. Rollback, varsa FK'nın işaret ettiği
statü kodunu geçici kolona geri yazar. Eski `lost` ve `callback` kodlarına bağlı
CHECK'ler kaldırılır; veri tabanlı koşul/guard uygulaması WP-09'a aittir.

## Sonuçlar

İş akışı yapılandırması kod yayını olmadan değişebilir, fakat eski statü ve
geçişler fiziksel olarak kaybolmaz. Açık dosyalar yürürlükteki akışı kullanırken
her statü girişi ilgili revizyona bağlanabilir. `deals.status_changed_at` yalnız
pano sorgusu önbelleği olarak kalır; süre hesabının gerçek kaynağı
`status_history` olur.
