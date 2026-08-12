# WP-20 geri dönüş tatbikatı kanıtı

Bu dosya otomatik/yerel tatbikatın gerçek çıktısıyla doldurulur; canlı sağlayıcı
kimliği, bucket adı, erişim anahtarı veya parola yazılmaz.

## Tatbikat kaydı

- Tarih (UTC): 12.08.2026 15:08–15:09
- Kaynak: şifreli yerel `restic` test repository'si
- Hedef: üretimden bağımsız boş Docker volume'u
- PostgreSQL: 17
- Tam yedek snapshot: `b42d5a99`
- WAL snapshot: `7b2ec08c`
- Sonuç: başarılı

## Gerçek çıktı özeti

```text
pg_basebackup: 34383/34383 kB (100%), 1/1 tablespace
snapshot b42d5a99 saved (postgres-base, 48.999 MiB)
snapshot 7b2ec08c saved (postgres-wal, 96.000 MiB)
restic check: no errors were found
restore: Restored 1616 files/dirs (48.999 MiB)
restore WAL: Restored 8 files/dirs (96.000 MiB)
RESTORE_HEALTH=healthy
migrations=18
statuses=18
audit_partitions=4
archive recovery complete
database system is ready to accept connections
```

İlk deneme `no pg_hba.conf entry for replication` ile reddedildi. Uygulama
süper kullanıcısını yedek servisine vermek yerine yalnız `REPLICATION LOGIN`
yetkili, `rolsuper=false` ayrı `tesvik_backup` rolü eklendi. İkinci deneme ve
boş volume'a geri dönüş başarılı oldu. Tatbikat yaklaşık bir dakika sürdü.
