# ADR-0002 · Statik analiz yapılandırması

## Durum

Kabul edildi

## Bağlam

PHPStan PHP tiplerini analiz eder ancak Laravel'in container çözümlemesi,
facade'ları, Eloquent modelleri ve dinamik framework API'leri hakkında doğrudan
bilgi sahibi değildir. Uzantı yalnız Composer bağımlılığı olarak kurulduğunda
etkinleştiği varsayılamaz; yerel ve CI analizlerinin aynı Laravel kurallarını
yüklediği açıkça güvence altına alınmalıdır.

Kod tabanı henüz küçük olduğu için teknik borcu gizleyecek bir baseline'a ihtiyaç
yoktur. Analizin framework'ü başlatması ve migration/model şemasını incelemesi
varsayılan bellek sınırını aşabilir; geliştirici ortamıyla CI arasında farklı
bellek davranışı istenmez.

## Karar

- Statik analiz PHPStan'ın Laravel uzantısı Larastan ile yapılır.
- `phpstan.neon`, `vendor/larastan/larastan/extension.neon` dosyasını açıkça
  include eder. Böylece Composer eklenti keşfine örtük olarak güvenilmez.
- Başlangıç seviyesi 6'dır. Bu seviye eksik dönüş/property tipleri ve hatalı
  union kullanımları gibi yüksek değerli sorunları baştan yakalarken henüz
  şekillenmemiş array shape ve generic sözleşmeleri için geçici açıklama yükü
  oluşturmaz.
- Baseline oluşturulmaz. Her yeni ihlal düzeltilir veya kural değişikliği ayrı
  bir ADR ile gerekçelendirilir.
- Bellek sınırı 512 MB'dir. Hem `make analyse` hem GitHub Actions aynı
  `vendor/bin/phpstan analyse --memory-limit=512M` komutunu çalıştırır; level,
  yollar ve Larastan aktivasyonu ortak `phpstan.neon` dosyasından gelir.
- WP-13 tamamlandıktan sonra ve WP-14 başlamadan önce level 8 denemesi yapılır.
  Level 8'e yükseltme; tüm yeni ihlaller baseline olmadan giderildiğinde ve CI
  analizi 15 dakikalık toplam iş sınırı içinde kaldığında kalıcılaştırılır.

## Sonuçlar

Laravel'e özgü hatalar yerel ve CI ortamında aynı şekilde yakalanır. Level 6
geliştirme başlangıcında uygulanabilir bir katılık sağlar; level 8 hedefi somut
bir paket sınırına bağlanır. 512 MB sınırı kaynak kullanımını öngörülebilir
tutar, fakat kod tabanı büyürse paralellik ve bellek kullanımı yeniden ölçülür.

## Alternatifler

- Yalnız PHPStan kullanmak, Laravel'in dinamik API'lerinde yanlış pozitif ve
  kaçırılan framework hataları doğuracağı için seçilmedi.
- Larastan'ı yalnız Composer'a ekleyip otomatik keşfe bırakmak, uzantının kurulu
  fakat pasif kalabilmesi nedeniyle seçilmedi.
- Level 8 ile başlamak, henüz oluşmamış domain koleksiyonları için erken
  annotation yükü yaratacağı için ertelendi.
- Baseline üretmek, sıfır kod borcunu kalıcı istisnalara dönüştüreceği için
  reddedildi.
- Sınırsız veya ortama göre farklı bellek limiti, yerel/CI sapması ve kontrolsüz
  kaynak kullanımı yaratacağı için seçilmedi.
