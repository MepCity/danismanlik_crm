# ADR-0005: Servis hata dönüş deseni

- Durum: Kabul edildi
- Tarih: 2026-08-10

## Bağlam

Servislerin kimi zaman sonuç nesnesi, kimi zaman boolean ve kimi zaman genel
exception döndürmesi çağıran HTTP, Filament ve gelecekteki API katmanlarında
tutarsız hata işleme üretir.

## Karar

Başarılı servis çağrıları kendi açık dönüş tipini kullanır. Beklenen iş kuralı
ihlalleri `App\Support\Exceptions\DomainException` tabanından türeyen tipli
exception olarak yükseltilir. Her exception kullanıcı metnini koda gömmek yerine
bir `lang/tr/domain.php` çeviri anahtarı ve parametreleri sağlar.

Henüz uygulanmamış paket iskeletleri domain hatası değildir; örnek
`StatusMachine` bu nedenle talimatta istendiği gibi `LogicException('WP-09')`
fırlatır. İlgili paket yazıldığında bu geçici gövde kaldırılır.

## Sonuçlar

Sunum katmanları tek bir domain hata ailesini çevirebilir. Başarı için fazladan
sonuç sarmalayıcısı taşınmaz; hata ayrıntıları çeviri anahtarıyla kararlı kalır.
