# ADR-0018: Yönetim ekranı mutasyon akışları

- **Durum:** Kabul edildi
- **Tarih:** 13.08.2026
- **İlgili:** PLAN.md K-05, K-09, §6 ve §7.2.1

## Bağlam

Yöneticinin program şablonlarını ve iş akışını kod yazmadan değiştirebilmesi gerekir. Buna karşılık ham JSON girişi, çıkışsız statü üretme ve eski program sürümünü yerinde değiştirme açık dosyaları kilitleyebilir veya sözleşmesel geçmişi bozabilir. Filament Resource içinde iş kuralı yazılması da mobil ve portal katmanlarının aynı davranışı kullanmasını engeller.

## Karar

### Koşul düzenleyici

Koşul formu ham JSON göstermez. Alanlar sabit bir katalogdan (`company.city`, `deal.requested_amount`, `deal.required_documents.status`), operatörler mevcut DSL'den (`in`, `gt`, `all_in`) seçilir. Değer bileşeni alan tipine göre il çoklu seçimi, sayı veya belge durumu çoklu seçimi olur. Form durumu kayıtta mevcut `{"all": [...]}` biçimine çevrilir.

Kaydetmeden önce aynı tanım hem alan/operatör kataloğuna hem mevcut `ConditionEvaluator` uygulamasına doğrulatılır. Bilinmeyen alan veya operatör sessizce `false` olmaz ve veritabanına ulaşmaz. Önizleme aynı tanımdan Türkçe cümle üretir; böylece kaydedilen kural ile kullanıcıya gösterilen anlam ayrışmaz.

### Yetim kontrolü ve toplu geçiş

Statü ve geçiş pasifleştirme düğmeleri doğrudan model güncellemez; WP-09'daki `WorkflowDeactivationService` çağrılır. Servis mevcut `OrphanTransitionInspector` sonucunda etkilenen kayıt bulursa hedef statü olmadan transaction'ı geri alır. Hedef aynı akış tipinde, aktif ve kaynak statüden farklı olmalıdır.

Kontrollü toplu geçiş, etkilenen kayıtları kilitler; açık `status_history` satırlarını kapatır, hedef geçmiş satırlarını açar ve yapılandırmayı pasifleştirir. İşlem bir `workflow_revisions` anlık görüntüsü ve bütün toplu işlemi özetleyen tek `workflow.bulk_transition` aktivitesi üretir. Tek tek kayıtlar için ayrı kullanıcı aktivitesi yazılmaz. Veritabanı trigger denetimi satır değişikliklerini bağımsız olarak yakalamaya devam eder.

Statü veya geçişin diğer düzenlemeleri de aynı mevcut servis üzerinden ve zorunlu gerekçeyle yeni workflow revizyonu oluşturur. Resource yalnızca form verisini servise taşır.

### Program sürümü kopyalama

Kopyalama açıkça seçilen **Önceki sürümden kopyala** aksiyonudur; yeni sürüm oluşturmanın sessiz yan etkisi değildir. Aksiyon tek transaction içinde yeni `program_versions` satırını ve her evrak için yeni `doc_templates` satırlarını oluşturur. Kaynak sürüm ve şablonlar yerinde değiştirilmez. Açık dosyaların `program_version_id`, `source_program_version_id` ve anlık görüntü alanları kaynak sürümde kalır.

Program sürümlerinde silme eylemi yoktur; model düzeyinde de silme reddedilir. Kullanım dışı sürüm `is_active=false` yapılır.

## Sonuçlar

- Koşul DSL kapsamı genişletilecekse alan kataloğu, değer bileşeni, önizleme çevirisi ve kanıt testi birlikte değişir.
- Toplu geçiş normal `StatusMachine` guard/condition akışı değildir; yöneticinin gerekçeli yapılandırma göçüdür ve ayrı tek olayla görünür olur.
- Yeni program çağrısı eski çağrı ve açık dosyalar için geriye dönük değişiklik yaratmaz.
- Kullanıcı ve rol değişiklikleri Resource içinde ilişki senkronlamaz; gerekçeli domain action'ları `role_permission_history` kaydıyla birlikte transaction yürütür.
