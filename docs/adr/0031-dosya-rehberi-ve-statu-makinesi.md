# ADR-0031: Dosya rehberi ve statü makinesi ayrımı

- Durum: Kabul edildi
- Tarih: 21.08.2026

## Bağlam

Hizmet dönemleri yönetici tarafından seçilen uygulama rehberinin anlık görüntüsünü taşır. Dosya ekranının doğrudan bu alanı okuması, program sürümü sonradan düzenlenirse açık ve geçmiş dosyalarda görülen talimatı değiştirebilir. Evrak şablonlarında olduğu gibi dosya açılışındaki bağlamın ayrıca korunması gerekir.

Uygulama rehberi sıralı işlem, bekleme ve karar notları sunar. Bu metinlerin dosya statülerini yöneten geçiş kurallarıyla karıştırılması yetki ve koşul kontrollerinin atlanmasına yol açabilir.

## Karar

1. Yeni dosya oluşturulurken `program_versions.workflow_snapshot`, aynı transaction içinde `deals.workflow_snapshot` alanına kopyalanır.
2. Fırsat dönüşümü ve firma üzerinden müşteri akışı aynı `ChecklistDealGateway` oluşturma yolunu kullanır; iki giriş de aynı kopyalama sözleşmesine tabidir.
3. Mevcut dosyalar migration sırasında bağlı program sürümündeki anlık görüntüyle geriye dönük doldurulur.
4. Dosya detayı yalnız `deals.workflow_snapshot` değerini gösterir. Program sürümündeki sonraki değişiklikler dosya rehberini etkilemez.
5. Rehber bilgilendiricidir. Statülerin izin, koşul ve yan etkileri yalnız `transitions` verisi ile `StatusMachine` tarafından uygulanır; rehber bir statü geçişi başlatmaz veya geçişi geçersiz kılamaz.

## Sonuçlar

- Her dosya açıldığı andaki sıra, başlık, rehber metni, dikkat notu ve adım tipini kalıcı olarak taşır.
- Yönetici yeni hizmet dönemleri için rehberi değiştirebilir; geçmiş operasyon kaydı değişmez.
- Rehber ve statü hattı birbirini açıklayabilir fakat birbirinin gerçek kaynağı olmaz.
