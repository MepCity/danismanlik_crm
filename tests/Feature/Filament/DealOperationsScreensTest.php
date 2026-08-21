<?php

declare(strict_types=1);

use App\Domain\Collaboration\Jobs\SendNotificationEmail;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Document\Models\File;
use App\Domain\Document\Services\DocumentUploadService;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Pages\DealBoard;
use App\Filament\Pages\DealDetail;
use App\Filament\Pages\PendingAssignments;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    app()->setLocale('tr');
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    config()->set('documents.disk', 's3');
    Queue::fake();
});

/** @return array{marketing: User, officer: User, pm: User, company: Company, deal: Deal} */
function operationsFixture(string $suffix = 'ana'): array
{
    $marketing = User::factory()->create(['email' => "pazarlama-{$suffix}@example.invalid"]);
    $marketing->assignRole('Pazarlama');
    $officer = User::factory()->create(['email' => "yetkili-{$suffix}@example.invalid"]);
    $officer->assignRole('Şirket Yetkilisi');
    $pm = User::factory()->create(['email' => "pm-{$suffix}@example.invalid"]);
    $pm->assignRole('Proje Yöneticisi');
    $company = Company::query()->create(['legal_name' => "Kurgusal Atlas {$suffix}", 'city' => 'Ankara']);
    $status = Status::query()->where('type', 'deal')->where('code', 'collecting_documents')->sole();
    $programVersion = ProgramVersion::query()->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $programVersion->id,
        'workflow_snapshot' => $programVersion->workflow_snapshot,
        'reference_no' => 'KRG-'.mb_strtoupper($suffix),
        'status_id' => $status->id,
        'status_changed_at' => now()->subDays(8),
        'pm_user_id' => $pm->id,
        'opened_by_user_id' => $officer->id,
        'priority' => 'normal',
    ]);
    StatusHistory::query()->create([
        'deal_id' => $deal->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'workflow_revision_id' => WorkflowRevision::query()->firstOrFail()->id,
        'entered_at' => now()->subDays(8),
        'changed_by' => $officer->id,
    ]);

    return compact('marketing', 'officer', 'pm', 'company', 'deal');
}

function operationDocument(Deal $deal, string $name, string $status = 'requested'): DealDocument
{
    return DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_program_version_id' => $deal->program_version_id,
        'name_snapshot' => $name,
        'required_snapshot' => true,
        'status' => $status,
    ]);
}

it('pano kapsamını uygular ve kapsam dışı dosya URL erişimini 403 ile reddeder', function (): void {
    $target = operationsFixture('hedef');
    $own = operationsFixture('kendi');
    $own['deal']->update(['opened_by_user_id' => $target['marketing']->id, 'pm_user_id' => null]);
    Auth::login($target['marketing']);

    Livewire::test(DealBoard::class)
        ->assertSee($own['company']->legal_name)
        ->assertDontSee($target['company']->legal_name);

    Livewire::test(DealDetail::class, ['deal' => $target['deal']->id])->assertForbidden();
});

it('dosya panosunda kart detayını açar ve geçerli bırakmada statüyü değiştirir', function (): void {
    $fixture = operationsFixture('surukle');
    $assigned = Status::query()->where('type', 'deal')->where('code', 'pm_assigned')->sole();
    $collecting = Status::query()->where('type', 'deal')->where('code', 'collecting_documents')->sole();
    $fixture['deal']->update(['status_id' => $assigned->id]);
    Auth::login($fixture['officer']);

    Livewire::test(DealBoard::class)
        ->call('openDeal', $fixture['deal']->id)
        ->assertSet('selectedDealId', $fixture['deal']->id)
        ->assertSee('Kurgusal Atlas surukle')
        ->assertSee('deal-detail-drawer')
        ->call('moveDeal', $fixture['deal']->id, $collecting->id)
        ->assertHasNoErrors();

    expect($fixture['deal']->refresh()->status_id)->toBe($collecting->id);
});

it('dosya panosunda atama isteyen bırakmayı yönetici seçimine yönlendirir', function (): void {
    $fixture = operationsFixture('surukle-atama');
    $awaiting = Status::query()->where('type', 'deal')->where('code', 'awaiting_assignment')->sole();
    $assigned = Status::query()->where('type', 'deal')->where('code', 'pm_assigned')->sole();
    $fixture['deal']->update(['status_id' => $awaiting->id, 'pm_user_id' => null]);
    Auth::login($fixture['officer']);

    Livewire::test(DealBoard::class)
        ->call('moveDeal', $fixture['deal']->id, $assigned->id)
        ->assertSet('transitionDealId', $fixture['deal']->id)
        ->assertSet('transitionTargetId', $assigned->id)
        ->assertSee('deal-assignment-drawer');
});

it('pano türev sayaçlarını deal_documents satırlarından doğru hesaplar', function (): void {
    $fixture = operationsFixture('sayac');
    foreach (['accepted', 'accepted', 'accepted', 'under_review', 'expired', 'requested', 'to_request', 'not_required'] as $index => $status) {
        operationDocument($fixture['deal'], 'Kurgusal Evrak '.($index + 1), $status);
    }
    Auth::login($fixture['officer']);

    Livewire::test(DealBoard::class)
        ->assertSee('5/8 geldi · 2 eksik · 1 incelemede · 1 süresi doldu');
});

it('dosya detayında statü sorumlu ve evrak ilerlemesini özetler', function (): void {
    $fixture = operationsFixture('ozet');
    operationDocument($fixture['deal'], 'Kurgusal Tamamlanan Belge', 'accepted');
    operationDocument($fixture['deal'], 'Kurgusal Eksik Belge', 'requested');
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->assertSee('Dosya özeti')
        ->assertSee('Evrak ilerlemesi')
        ->assertSee('1 / 2 tamamlandı')
        ->assertSee('1 eksik zorunlu evrak')
        ->assertSee('KOSGEB başvuru rehberi')
        ->assertSee('Belgeleri topla ve doğrula')
        ->assertSee('Değerlendirme ve kurul sonucunu bekle')
        ->assertSee($fixture['pm']->name);
});

it('reddedilen geçişte StatusMachine mesajını değiştirmeden ekranda gösterir', function (): void {
    $fixture = operationsFixture('gecis');
    operationDocument($fixture['deal'], 'Kurgusal Findeks Raporu');
    operationDocument($fixture['deal'], 'Kurgusal Fizibilite Raporu');
    $target = Status::query()->where('type', 'deal')->where('code', 'preparing_application')->sole();
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'process')
        ->call('transitionDeal', $target->id)
        ->assertSee('2 zorunlu evrak eksik: Kurgusal Findeks Raporu, Kurgusal Fizibilite Raporu.');
});

it('ret ve yeni sürüm kararlarını gerekçesiz form doğrulamasından geçirmez', function (string $target): void {
    $fixture = operationsFixture('gerekce-'.$target);
    $document = operationDocument($fixture['deal'], 'Kurgusal İnceleme Belgesi', 'under_review');
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('decisionDocumentId', $document->id)
        ->set('decisionTarget', $target)
        ->set('decisionReason', '')
        ->call('decide')
        ->assertHasErrors(['decisionReason' => 'required']);
})->with(['rejected', 'new_version_expected']);

it('yükleme sürümü artırır ve eski sürümü checklist geçmişinde erişilebilir tutar', function (): void {
    $fixture = operationsFixture('surum');
    $document = operationDocument($fixture['deal'], 'Kurgusal Fizibilite Raporu');
    $service = app(DocumentUploadService::class);
    $service->upload($document->id, UploadedFile::fake()->createWithContent('rapor-v1.pdf', "%PDF-1.4\nKurgusal v1\n%%EOF"), $fixture['officer']->id);
    $service->upload($document->id, UploadedFile::fake()->createWithContent('rapor-v2.pdf', "%PDF-1.4\nKurgusal v2\n%%EOF"), $fixture['officer']->id);
    File::query()->where('deal_document_id', $document->id)->update(['scan_result' => 'clean']);
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'documents')
        ->assertSee('Kurgusal Fizibilite Raporu — Sürüm 1')
        ->assertSee('Kurgusal Fizibilite Raporu — Sürüm 2');
    expect(File::query()->where('deal_document_id', $document->id)->pluck('version_no')->all())->toBe([1, 2]);
});

it('pazarlamacı kendi dosyasında belge yükleyip tekil ve toplu indirme işlemlerini görür', function (): void {
    $fixture = operationsFixture('pazarlama-belge');
    $fixture['deal']->update(['opened_by_user_id' => $fixture['marketing']->id]);
    $document = operationDocument($fixture['deal'], 'Kurgusal Pazarlama Belgesi');
    $file = app(DocumentUploadService::class)->upload(
        $document->id,
        UploadedFile::fake()->createWithContent('pazarlama.pdf', "%PDF-1.4\nKurgusal pazarlama\n%%EOF"),
        $fixture['marketing']->id,
    )->file;
    $file->update(['scan_result' => 'clean']);
    Auth::login($fixture['marketing']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'documents')
        ->assertSee('Belge yükle')
        ->assertSee('Son sürümü indir')
        ->assertSee('Tüm güncel belgeleri indir')
        ->set('uploadDocumentId', $document->id)
        ->set('upload', UploadedFile::fake()->createWithContent('pazarlama-yeni.pdf', "%PDF-1.4\nKurgusal yeni sürüm\n%%EOF"))
        ->call('uploadDocument')
        ->assertHasNoErrors();

    expect(File::query()->where('deal_document_id', $document->id)->count())->toBe(2);
});

it('bekleyen öneriyi iki ekranda gösterir ve her iki kararı uygular', function (): void {
    $fixture = operationsFixture('oneri');
    $acceptedDocument = operationDocument($fixture['deal'], 'Kurgusal Koşullu Belge A');
    $rejectedDocument = operationDocument($fixture['deal'], 'Kurgusal Koşullu Belge B');
    $accepted = DocumentRequirementSuggestion::query()->create([
        'deal_document_id' => $acceptedDocument->id, 'reason' => 'condition_no_longer_matches',
        'reason_parameters' => [], 'status' => 'pending',
    ]);
    $rejected = DocumentRequirementSuggestion::query()->create([
        'deal_document_id' => $rejectedDocument->id, 'reason' => 'condition_no_longer_matches',
        'reason_parameters' => [], 'status' => 'pending',
    ]);
    Auth::login($fixture['officer']);

    Livewire::test(DealBoard::class)->assertSee('2 bekleyen gereklilik önerisi');
    $detail = Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'documents')
        ->assertSee('Sistem bu belgenin artık gerekli olmadığını öneriyor.')
        ->call('decideSuggestion', $accepted->id, true)
        ->call('decideSuggestion', $rejected->id, false);

    $detail->assertOk();
    expect($acceptedDocument->refresh()->status)->toBe('not_required')
        ->and($rejectedDocument->refresh()->status)->toBe('requested');
});

it('eksik evrak e-postasını doğru izinli kontağa kuyruğa alır', function (): void {
    $fixture = operationsFixture('eposta');
    Contact::query()->create([
        'company_id' => $fixture['company']->id, 'full_name' => 'Kurgusal Firma Yetkilisi',
        'data_source' => 'other',
        'email' => 'firma-yetkilisi@example.invalid', 'is_primary' => true, 'is_active' => true,
        'consent_email' => true, 'do_not_call' => false,
    ]);
    operationDocument($fixture['deal'], 'Kurgusal Eksik Findeks', 'to_request');
    operationDocument($fixture['deal'], 'Kurgusal Eksik Fizibilite', 'requested');
    operationDocument($fixture['deal'], 'Kurgusal Kabul Belgesi', 'accepted');
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->call('sendMissingDocuments');

    $notification = Notification::query()->where('type', 'deal.missing_documents_requested')->sole();
    expect($notification->recipient_email)->toBe('firma-yetkilisi@example.invalid')
        ->and($notification->body)->toContain('Kurgusal Eksik Findeks', 'Kurgusal Eksik Fizibilite')
        ->and($notification->body)->not->toContain('Kurgusal Kabul Belgesi');
    Queue::assertPushed(SendNotificationEmail::class);
});

it('bildirimde sistem kullanıcısı ile harici alıcıdan tam birini zorunlu tutar', function (): void {
    $user = User::factory()->create();

    expect(fn () => Notification::query()->create([
        'user_id' => $user->id,
        'recipient_email' => 'harici@example.invalid',
        'type' => 'invalid.recipient',
        'title' => 'Kurgusal başlık',
        'body' => 'Kurgusal gövde',
        'channel' => 'email',
    ]))->toThrow(QueryException::class);
});

it('izinsiz rol checklist aksiyonlarını görmez ve doğrudan çağıramaz', function (): void {
    $fixture = operationsFixture('yetki');
    $document = operationDocument($fixture['deal'], 'Kurgusal Yetki Belgesi', 'uploaded');
    $fixture['deal']->update(['opened_by_user_id' => $fixture['marketing']->id]);
    Auth::login($fixture['marketing']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'documents')
        ->assertDontSee('İncelemeye al')
        ->call('startReview', $document->id)
        ->assertForbidden();
});

it('dosya görüşmesini dosyadan ayrı bir interaction satırı olarak kaydeder', function (): void {
    $fixture = operationsFixture('dosya-gorusme');
    $statusId = $fixture['deal']->status_id;
    Auth::login($fixture['officer']);

    Livewire::test(DealDetail::class, ['deal' => $fixture['deal']->id])
        ->set('activeTab', 'interactions')
        ->set('interactionType', 'meeting')
        ->set('interactionOccurredAt', now()->format('Y-m-d\TH:i'))
        ->set('interactionOutcome', 'Kurgusal toplantı sonucu')
        ->call('addInteraction')
        ->assertHasNoErrors()
        ->assertSee('Kurgusal toplantı sonucu');

    expect(Interaction::query()->where('deal_id', $fixture['deal']->id)->whereNull('lead_id')->count())->toBe(1)
        ->and($fixture['deal']->refresh()->status_id)->toBe($statusId);
});

it('şirket yetkilisi atama ekranında işi inceleyip proje yöneticisine devreder', function (): void {
    $fixture = operationsFixture('atama-ekrani');
    $awaiting = Status::query()->where('type', 'deal')->where('is_initial', true)->sole();
    $fixture['deal']->update(['status_id' => $awaiting->id, 'pm_user_id' => null]);
    Auth::login($fixture['officer']);

    Livewire::test(PendingAssignments::class)
        ->assertSee($fixture['company']->legal_name)
        ->assertSee('Proje yöneticisi iş yükü')
        ->set("projectManagerIds.{$fixture['deal']->id}", $fixture['pm']->id)
        ->call('assign', $fixture['deal']->id)
        ->assertHasNoErrors();

    expect($fixture['deal']->refresh()->pm_user_id)->toBe($fixture['pm']->id);
});
