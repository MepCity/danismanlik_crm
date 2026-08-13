<?php

declare(strict_types=1);

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Pages\DealDetail;
use App\Filament\Pages\LeadDetail;
use App\Livewire\CollaborationComments;
use App\Livewire\CollaborationTimeline;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

/** @return array{owner: User, officer: User, outsider: User, deal: Deal, lead: Lead, document: DealDocument} */
function collaborationScreenFixture(): array
{
    $owner = User::factory()->create(['name' => 'Kurgusal Ece Uzman', 'email' => 'ece-ekran@example.invalid']);
    $owner->assignRole('Pazarlama');
    $officer = User::factory()->create(['name' => 'Kurgusal Bora Yetkili', 'email' => 'bora-ekran@example.invalid']);
    $officer->assignRole('Şirket Yetkilisi');
    $outsider = User::factory()->create(['name' => 'Kurgusal Gizli Kullanıcı', 'email' => 'gizli-ekran@example.invalid']);
    $outsider->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Pusula Teknoloji', 'city' => 'Ankara']);
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status_id' => Status::query()->where('type', 'lead')->firstOrFail()->id,
    ]);
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => ProgramVersion::query()->firstOrFail()->id,
        'reference_no' => 'KRG-WP18',
        'status_id' => Status::query()->where('type', 'deal')->firstOrFail()->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $owner->id,
        'pm_user_id' => $officer->id,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_program_version_id' => $deal->program_version_id,
        'name_snapshot' => 'Kurgusal Başvuru Belgesi',
        'required_snapshot' => true,
        'status' => 'requested',
    ]);

    return compact('owner', 'officer', 'outsider', 'deal', 'lead', 'document');
}

it('mention önerisinde yalnız özneyi görebilen kullanıcıları gösterir', function (): void {
    $fixture = collaborationScreenFixture();
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->assertSee('Kurgusal Bora Yetkili')
        ->assertDontSee('Kurgusal Gizli Kullanıcı');
});

it('müşteriye açık görünümden iç notu çıkarır ve görünürlük varsayılanını güvenli tutar', function (): void {
    $fixture = collaborationScreenFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    app(CommentService::class)->create($fixture['owner'], $subject, 'Yalnız ekip içeriği.', 'internal');
    app(CommentService::class)->create($fixture['owner'], $subject, 'Müşteri görünür içeriği.', 'customer');
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->assertSet('visibility', 'internal')
        ->assertSee('İç not');
    Livewire::test(CollaborationComments::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id, 'audience' => 'customer'])
        ->assertSee('Müşteri görünür içeriği.')
        ->assertDontSee('Yalnız ekip içeriği.');
});

it('mention formatını seçimle ekler ve okurken kişi adı olarak gösterir', function (): void {
    $fixture = collaborationScreenFixture();
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->set('mentionUserId', $fixture['officer']->id)
        ->call('addMention')
        ->assertSet('body', " @[Kurgusal Bora Yetkili](user:{$fixture['officer']->id}) ")
        ->set('body', "Kontrol eder misiniz? @[Kurgusal Bora Yetkili](user:{$fixture['officer']->id})")
        ->call('save')
        ->assertSee('@Kurgusal Bora Yetkili')
        ->assertDontSee("(user:{$fixture['officer']->id})");
});

it('düzenlenen yorum işaretini ve tek seviye yanıtı gösterir', function (): void {
    $fixture = collaborationScreenFixture();
    $subject = new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id);
    $comment = app(CommentService::class)->create($fixture['owner'], $subject, 'İlk yorum.');
    app(CommentService::class)->edit($fixture['owner'], $comment, 'Düzenlenmiş yorum.');
    app(CommentService::class)->create($fixture['officer'], $subject, 'Bir seviye yanıt.', parentId: $comment->id);
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->assertSee('Düzenlenmiş yorum.')
        ->assertSee('Bir seviye yanıt.')
        ->assertSee('düzenlendi');
});

it('kapsam dışı dosyanın zaman tünelini 403 ile reddeder', function (): void {
    $fixture = collaborationScreenFixture();
    Auth::login($fixture['outsider']);

    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->assertForbidden();
});

it('ham JSON yerine yedek cümleyi ve değişmez statü etiketlerini gösterir', function (): void {
    $fixture = collaborationScreenFixture();
    Activity::query()->create([
        'actor_id' => $fixture['owner']->id,
        'deal_id' => $fixture['deal']->id,
        'action' => 'legacy.unknown_event',
        'payload' => ['secret' => 'ham-json-değeri'],
        'source' => 'user',
    ]);
    Activity::query()->create([
        'actor_id' => $fixture['owner']->id,
        'deal_id' => $fixture['deal']->id,
        'action' => 'deal.status_changed',
        'payload' => ['from_status' => ['label' => 'Eski anlık etiket'], 'to_status' => ['label' => 'Yeni anlık etiket']],
        'source' => 'user',
    ]);
    $fixture['deal']->status->update(['label' => 'Sonradan değiştirilen etiket', 'is_active' => false]);
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->assertSee('legacy unknown event işlemini gerçekleştirdi')
        ->assertSee('Eski anlık etiket')
        ->assertSee('Yeni anlık etiket')
        ->assertDontSee('ham-json-değeri')
        ->assertDontSee('Sonradan değiştirilen etiket');
});

it('zaman tüneli filtrelerini dosya fırsat ve evrak öznelerinde uygular', function (): void {
    $fixture = collaborationScreenFixture();
    foreach ([
        ['deal_id' => $fixture['deal']->id, 'action' => 'deal.status_changed'],
        ['deal_document_id' => $fixture['document']->id, 'action' => 'document.uploaded'],
        ['lead_id' => $fixture['lead']->id, 'action' => 'lead.status_changed'],
    ] as $row) {
        Activity::query()->create([...$row, 'actor_id' => $fixture['owner']->id, 'payload' => [], 'source' => 'user']);
    }
    app(CommentService::class)->create($fixture['owner'], new SubjectReference(CollaborationSubjectType::Deal, $fixture['deal']->id), 'Filtre yorumu.');
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id])
        ->call('setFilter', 'comment')->assertSee('Filtre yorumu.')->assertDontSee('statüyü')
        ->call('setFilter', 'document')->assertSee('Kurgusal Başvuru Belgesi')->assertDontSee('Filtre yorumu.');
    Auth::login($fixture['officer']);
    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'lead', 'subjectId' => $fixture['lead']->id])->assertSee('fırsat statüsünü');
    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal_document', 'subjectId' => $fixture['document']->id])->assertSee('Kurgusal Başvuru Belgesi');
});

it('zaman tüneli render sorgu sayısını kayıt sayısından bağımsız tutar', function (): void {
    $fixture = collaborationScreenFixture();
    foreach (range(1, 25) as $index) {
        Activity::query()->create([
            'actor_id' => $fixture['owner']->id, 'deal_id' => $fixture['deal']->id,
            'action' => 'legacy.event_'.$index, 'payload' => [], 'source' => 'user',
        ]);
    }
    Auth::login($fixture['owner']);
    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id, 'perPage' => 5]);
    $fiveQueries = count(DB::getQueryLog());
    DB::flushQueryLog();
    Livewire::test(CollaborationTimeline::class, ['subjectType' => 'deal', 'subjectId' => $fixture['deal']->id, 'perPage' => 25]);
    $twentyFiveQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($fiveQueries)->toBe($twentyFiveQueries)->toBeLessThanOrEqual(16);
});

it('dosya ve fırsat detayında yorum ile zaman tüneli sekmelerini bağlar', function (): void {
    $fixture = collaborationScreenFixture();
    Auth::login($fixture['owner']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])->set('activeTab', 'comments')->assertSee('Yeni yorum');
    Livewire::test(LeadDetail::class, ['lead' => $fixture['lead']->id])->set('activeTab', 'history')->assertSee('İşlem geçmişi');
});
