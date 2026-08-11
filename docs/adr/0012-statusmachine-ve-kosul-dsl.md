# ADR-0012: Statü makinesi ve koşul DSL'i

- **Durum:** Kabul edildi
- **Tarih:** 11.08.2026
- **İlgili kararlar:** K-03, K-05, K-09; PLAN.md §5.5, §5.6, §8

## Bağlam

Statü ve geçişler veritabanında yapılandırılır. Aynı koşul biçimi geçişlerde
ve evrak şablonlarında kullanıldığı için iki ayrı değerlendirici zamanla farklı
sonuç üretir. Geçişin guard, condition ve effect adımları ayrı transaction'lara
dağıtılırsa dosyanın güncel statüsü, süre geçmişi, aktivite akışı ve outbox
birbirinden kopabilir. Ayrıca bir geçişi pasifleştirmek, o statüdeki kayıtları
çıkışsız bırakabilir.

## Karar

### Paylaşılan koşul sözleşmesi

`App\Support\Conditions\ConditionEvaluator`, sorgu yapmayan bir
`ConditionContext` alır. Alanların veritabanından hazırlanması ilgili domain
servislerindedir; değerlendirici yalnızca hazır bağlamı işler. WP-10 aynı
arayüzü ve uygulamayı kullanacaktır.

| Biçim | Anlam | Değer sözleşmesi |
|---|---|---|
| `all` | Alt kuralların tamamı doğru | Boş olmayan kural listesi |
| `any` | Alt kurallardan en az biri doğru | Boş olmayan kural listesi |
| `in` | Tek değer izin verilen kümede | Sağ taraf liste |
| `gt` | Sayısal değer eşikten büyük | İki taraf sayısal |
| `all_in` | Koleksiyondaki her değer izin verilen kümede | İki taraf liste |

Alan yolu noktayla ayrılır. Bağlam bugün `company.*`, `deal.*` ve
`deal.required_documents.status` koleksiyon yolunu hazırlar. Çözümleyici bir
listeye geldiğinde kalan yolu her elemana uygular. Bilinmeyen operatör,
çözülemeyen alan ya da biçimi bozuk koşul **false sayılmaz**; tipli ve açık bir
yapılandırma hatası fırlatılır. Böylece hatalı yönetim verisi dosyaları sessizce
kilitlemez. `ConditionResult`, başarısız alan ve değerleri de taşır; statü
makinesi zorunlu evrakların anlık adlarını kullanıcı mesajına koyar.

### StatusMachine sözleşmesi ve transaction sınırı

Tek giriş noktası `transition(StatusTransition)` olur. İstek özne türünü
(`lead|deal`), özne kimliğini, **hedef statüyü**, aktörü ve isteğe bağlı
gerekçeyi taşır. Çağıran geçiş satırı kimliği göndermez: etkin `from → to`
satırını mevcut statüden servis bulur. Böylece eski/pasif bir geçiş kimliğinin
çağıran katmanca zorlanması mümkün olmaz. Önceki yalnız-dosya
`transition(dealId, transitionId)` iskeleti bu nedenle değiştirilmiştir.

Akış sırası şöyledir:

1. Mevcut özne ve statü satırı kilitlenir; terminal statüden çıkış reddedilir.
2. `from → to` geçişinin varlığı ve etkinliği doğrulanır.
3. `required_permission` guard'ı aktör üzerinde kontrol edilir.
4. JSONB condition paylaşılan değerlendiriciyle çalıştırılır.
5. Tek transaction içinde açık `status_history` kapanır, yeni satır o anki
   etiket ve yürürlükteki workflow revizyonuyla açılır, öznenin `status_id`
   alanı ve dosyanın `status_changed_at` önbelleği güncellenir, okunabilir
   aktivite yazılır ve domain olayı fırlatılarak outbox'a kaydedilir.

Domain servisinin diğer modüllerin modellerine erişmemesi için lead, firma,
belge, izin ve aktivite ihtiyaçları ilgili modüllerin servisleri ve ortak
DTO'ları üzerinden karşılanır.

### Yetim geçiş kontrolü

`OrphanTransitionInspector` pasifleştirme öncesinde yapılandırmayı değişiklik
uygulanmış gibi değerlendirir ve etkilenen statü, özne türü ve kayıt sayısını
`OrphanImpact` ile döndürür.

- Geçiş pasifleştirmesinde kaynak statünün başka etkin çıkışı kalıp kalmadığı
  kontrol edilir.
- Statü pasifleştirmesinde hem o statüdeki kayıtlar hem de tek etkin çıkışı bu
  statüye giden öncül statüler sayılır.
- Terminal statülerin çıkışsız olması normaldir; ancak pasifleştirilen terminal
  statünün içinde kayıt varsa yine etki olarak raporlanır.

`WorkflowDeactivationService` bu kontrolü aynı transaction'da çağıran zorunlu
mutasyon giriş noktasıdır. Etki boş değilse tipli domain hatasıyla işlemi
reddeder. Pasifleştirme başarılıysa değişiklik sonrası statü ve geçiş kümesini
aktör ve gerekçeyle yeni bir `workflow_revisions` satırına yazar. Toplu taşıma
ve yönetici kararı WP-15 kapsamındadır.

## Sonuçlar

Geçişin bütün görünür ve entegrasyon yan etkileri ya birlikte commit olur ya da
birlikte geri alınır. Aktivite ve geçmiş, statü etiketleri sonradan değişse de
okunur. Koşul motoru WP-10 tarafından yeniden yazılmayacak ortak bir sözleşmede
durur. İş akışı ayarları, içindeki açık kayıtları fark edilmeden yetim
bırakamaz.
