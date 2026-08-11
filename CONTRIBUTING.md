# Katkı rehberi

## Dal ve commit akışı

- Her iş paketi `main` dalından kendi `wp-XX-kisa-ad` dalına çıkar; `main` dalına
  doğrudan push yapılmaz.
- Değişiklikler mantıksal ve atomik commit'lere ayrılır.
- Commit başlığı Conventional Commits biçimindedir: `tip(kapsam): kısa emir`.
  Küçük harfle başlar, 50 karakteri geçmez ve noktayla bitmez.
- Commit ve PR metinlerinde araç/model imzası veya `Co-Authored-By` bulunmaz.
- Dal push edildikten sonra PR açılır; inceleme tamamlanmadan merge edilmez.

Bağlayıcı kuralların tamamı [`AGENTS.md`](AGENTS.md), proje kararları ve kapsamı
[`PLAN.md`](PLAN.md) içindedir.

## Mimari ve metin kuralları

- İş mantığı Filament Resource'ta ya da controller'da durmaz; `app/Domain`
  altındaki servis/domain katmanında yer alır.
- Kullanıcıya görünen her metin Türkçedir ve `lang/tr/` içinde tutulur; koda
  gömülmez. Kod, sınıf, tablo ve kolon adları İngilizcedir.
- Metin dosyaları UTF-8'dir. PostgreSQL veritabanı ve bağlantısı UTF-8 kullanır.
- Türkçe büyük/küçük harf dönüşümünde `strtolower()` / `strtoupper()` değil,
  uygun kodlamayla `mb_strtolower()` / `mb_strtoupper()` kullanılır; `I/ı` ve
  `İ/i` ayrımı test edilir.

## Kalite komutları

Tüm PHP araçları Docker container'ında çalışır:

```bash
make lint          # Pint biçim kontrolü
make lint-fix      # Pint ile düzelt
make analyse       # Larastan level 6
make test          # Pest + ayrı PostgreSQL test veritabanı
make test-coverage # HTML kapsama raporu (coverage/)
```

PR açmadan önce sırasıyla şunlar temiz geçmelidir:

```bash
make lint
make analyse
make test
```
