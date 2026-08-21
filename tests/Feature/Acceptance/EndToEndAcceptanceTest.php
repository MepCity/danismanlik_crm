<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\TransitionLead;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Jobs\ScanUploadedFile;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Services\DocumentRequestService;
use App\Domain\Document\Services\DocumentStatusService;
use App\Domain\Document\Services\DocumentUploadService;
use App\Domain\Program\Models\Program;
use App\Filament\Pages\DealDetail;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use App\Support\Outbox\Models\OutboxMessage;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-12 08:00:00');
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
    config()->set('documents.disk', 's3');
    config()->set('documents.max_size_kb', 1024);
    Storage::fake('s3');
    Queue::fake();
});

afterEach(fn () => Carbon::setTestNow());

/** @return array{marketing: User, project_manager: User, officer: User, admin: User} */
function acceptanceUsers(): array
{
    $users = [
        'marketing' => User::factory()->create(['name' => 'Kurgusal Pazarlama', 'email' => 'pazarlama@kabul.invalid']),
        'project_manager' => User::factory()->create(['name' => 'Kurgusal Proje Yöneticisi', 'email' => 'pm@kabul.invalid']),
        'officer' => User::factory()->create(['name' => 'Kurgusal Şirket Yetkilisi', 'email' => 'yetkili@kabul.invalid']),
        'admin' => User::factory()->create(['name' => 'Kurgusal Sistem Yöneticisi', 'email' => 'admin@kabul.invalid']),
    ];
    $users['marketing']->assignRole('Pazarlama');
    $users['project_manager']->assignRole('Proje Yöneticisi');
    $users['officer']->assignRole('Şirket Yetkilisi');
    $users['admin']->assignRole('Sistem Yöneticisi');

    $team = Team::query()->create(['name' => 'Kurgusal Kabul Ekibi', 'manager_id' => $users['project_manager']->id]);
    $team->members()->attach([
        $users['project_manager']->id => ['role' => 'manager'],
        $users['marketing']->id => ['role' => 'member'],
    ]);

    return $users;
}

/** @return array{company: Company, contact: Contact, lead: Lead} */
function acceptanceLead(User $marketing): array
{
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Kabul Teknoloji Ltd. Şti.',
        'city' => 'Ankara',
        'source' => 'form',
    ]);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal Firma Yetkilisi',
        'email' => 'firma@kabul.invalid',
        'data_source' => 'form',
        'is_primary' => true,
        'consent_email' => true,
    ]);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'call',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'explicit_consent',
        'source' => 'form',
        'effective_from' => now()->subDay(),
        'recorded_by' => $marketing->id,
    ]);
    $initial = Status::query()->where('type', 'lead')->where('is_initial', true)->sole();
    $revision = WorkflowRevision::query()->where('effective_from', '<=', now())->latest('effective_from')->sole();
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $marketing->id,
        'source' => 'form',
        'status_id' => $initial->id,
    ]);
    StatusHistory::query()->create([
        'lead_id' => $lead->id,
        'status_id' => $initial->id,
        'status_label_snapshot' => $initial->label,
        'workflow_revision_id' => $revision->id,
        'entered_at' => now(),
        'changed_by' => $marketing->id,
    ]);

    return compact('company', 'contact', 'lead');
}

function acceptanceStatus(string $type, string $code): Status
{
    return Status::query()->where('type', $type)->where('code', $code)->sole();
}

function transitionAcceptanceDeal(Deal $deal, string $targetCode, User $actor): void
{
    app(StatusMachineContract::class)->transition(new StatusTransition(
        SubjectType::Deal,
        $deal->id,
        acceptanceStatus('deal', $targetCode)->id,
        $actor->id,
    ));
}

function acceptancePdf(string $name, string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nKurgusal kabul belgesi {$content}\n%%EOF");
}

function acceptAcceptanceDocument(DealDocument $document, User $actor, string $version): void
{
    app(DocumentUploadService::class)->upload($document->id, acceptancePdf("kurgusal-{$document->id}-{$version}.pdf", $version), $actor->id);
    app(DocumentStatusService::class)->startReview($document->id, $actor->id);
    app(DocumentStatusService::class)->decide($document->id, 'accepted', null, $actor->id);
}

it('pazarlama aramasından onay kararına kadar zincirin tamamını tek testte yürütür', function (): void {
    Carbon::setTestNow('2026-08-12 09:00:00');
    $users = acceptanceUsers();
    $fixture = acceptanceLead($users['marketing']);
    $lead = $fixture['lead'];
    $leadFlow = app(TransitionLead::class);

    app(RecordInteraction::class)->forLead($lead->id, $users['marketing']->id, 'call', now(), 'interested', 'Kurgusal kabul görüşmesi.');
    expect(Activity::query()->where('lead_id', $lead->id)->where('action', 'interaction.recorded')->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where('table_name', 'interactions')->exists())->toBeTrue();

    foreach (['called', 'interested', 'proposal_sent'] as $code) {
        $leadFlow->handle($lead->id, acceptanceStatus('lead', $code)->id, $users['marketing']->id);
        expect($lead->refresh()->status->code)->toBe($code);
    }

    $version = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->sole()->versions()->where('call_period', '2026 çağrısı')->sole();
    $dealId = $leadFlow->handle($lead->id, acceptanceStatus('lead', 'won')->id, $users['marketing']->id, programVersionId: $version->id);
    $deal = Deal::query()->findOrFail($dealId);
    expect($lead->refresh()->converted_deal_id)->toBe($deal->id)
        ->and($deal->status->code)->toBe('awaiting_assignment')
        ->and($deal->program_version_id)->toBe($version->id)
        ->and($deal->documents()->count())->toBe(5)
        ->and(Notification::query()->where('deal_id', $deal->id)->where('user_id', $users['officer']->id)->where('type', 'deal.assignment_pending')->exists())->toBeTrue()
        ->and(Activity::query()->where('lead_id', $lead->id)->where('action', 'lead.converted')->exists())->toBeTrue();

    app(AssignDeal::class)->handle($deal->id, $users['project_manager']->id, acceptanceStatus('deal', 'pm_assigned')->id, $users['officer']->id);
    expect($deal->refresh()->pm_user_id)->toBe($users['project_manager']->id)
        ->and($deal->status->code)->toBe('pm_assigned')
        ->and(Activity::query()->where('deal_id', $deal->id)->where('action', 'deal.assigned')->exists())->toBeTrue()
        ->and(Notification::query()->where('deal_id', $deal->id)->where('user_id', $users['project_manager']->id)->where('type', 'deal.assigned')->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('event_name', 'deal.assigned')->exists())->toBeTrue();

    transitionAcceptanceDeal($deal, 'collecting_documents', $users['project_manager']);
    $initialDocumentIds = $deal->documents()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    app(DocumentRequestService::class)->markRequested($initialDocumentIds, $users['project_manager']->id);
    expect($deal->refresh()->document_requested_at?->toDateTimeString())->toBe('2026-08-12 09:00:00')
        ->and($deal->documents()->where('status', 'requested')->count())->toBe(5)
        ->and(Activity::query()->where('deal_id', $deal->id)->where('action', 'deal.documents_requested')->exists())->toBeTrue();

    $missingNames = $deal->documents()->orderBy('id')->pluck('name_snapshot')->implode(', ');
    expect(fn () => transitionAcceptanceDeal($deal, 'preparing_application', $users['project_manager']))
        ->toThrow(StatusTransitionRejected::class, '5 zorunlu evrak eksik: '.$missingNames)
        ->and($deal->refresh()->status->code)->toBe('collecting_documents');

    $rejected = $deal->documents()->orderBy('id')->firstOrFail();
    app(DocumentUploadService::class)->upload($rejected->id, acceptancePdf('kurgusal-ilk-surum.pdf', 'ilk'), $users['project_manager']->id);
    app(DocumentStatusService::class)->startReview($rejected->id, $users['project_manager']->id);
    app(DocumentStatusService::class)->decide($rejected->id, 'rejected', 'İmza sayfası eksik.', $users['project_manager']->id);
    expect($rejected->refresh()->status)->toBe('rejected')
        ->and($rejected->notes)->toBe('İmza sayfası eksik.')
        ->and(Activity::query()->where('deal_document_id', $rejected->id)->where('action', 'document.status_changed')->exists())->toBeTrue();

    app(DocumentUploadService::class)->upload($rejected->id, acceptancePdf('kurgusal-yeni-surum.pdf', 'ikinci'), $users['project_manager']->id);
    app(DocumentStatusService::class)->startReview($rejected->id, $users['project_manager']->id);
    app(DocumentStatusService::class)->decide($rejected->id, 'accepted', null, $users['project_manager']->id);
    expect($rejected->refresh()->status)->toBe('accepted')
        ->and($rejected->files()->orderBy('version_no')->pluck('version_no')->all())->toBe([1, 2]);

    $deal->documents()->whereKeyNot($rejected->id)->get()->each(
        fn (DealDocument $document) => acceptAcceptanceDocument($document, $users['project_manager'], 'ilk'),
    );
    expect($deal->refresh()->first_document_received_at)->not->toBeNull()
        ->and($deal->all_required_accepted_at)->not->toBeNull();

    $fixture['company']->update(['city' => 'Hatay']);
    $deal->update(['requested_amount' => '6000000.00']);
    $conditional = $deal->documents()->whereIn('name_snapshot', ['Hasar Durumu Belgesi', 'Fizibilite Raporu'])->get();
    expect($conditional)->toHaveCount(2)
        ->and($conditional->pluck('status')->unique()->all())->toBe(['to_request'])
        ->and($deal->refresh()->all_required_accepted_at)->toBeNull()
        ->and(Notification::query()->where('deal_id', $deal->id)->where('type', 'checklist.condition_documents_added')->count())->toBe(2);

    app(DocumentRequestService::class)->markRequested($conditional->modelKeys(), $users['project_manager']->id);
    $conditional->each(fn (DealDocument $document) => acceptAcceptanceDocument($document->refresh(), $users['project_manager'], 'kosullu'));
    expect($deal->documents()->whereNotIn('status', ['accepted', 'not_required'])->count())->toBe(0)
        ->and($deal->refresh()->all_required_accepted_at)->not->toBeNull();

    foreach (['preparing_application', 'awaiting_customer_approval', 'submitted', 'under_review'] as $code) {
        transitionAcceptanceDeal($deal, $code, $users['project_manager']);
        expect($deal->refresh()->status->code)->toBe($code);
    }
    $deal->update(['application_no' => 'KURGUSAL-2026-001', 'applied_at' => now()]);
    transitionAcceptanceDeal($deal, 'concluded', $users['project_manager']);
    $deal->update(['result_outcome' => 'approved', 'decided_at' => now()]);

    expect($deal->refresh()->status->code)->toBe('concluded')
        ->and($deal->result_outcome)->toBe('approved')
        ->and($deal->applied_at)->not->toBeNull()
        ->and($deal->decided_at)->not->toBeNull()
        ->and(StatusHistory::query()->where('deal_id', $deal->id)->count())->toBe(8)
        ->and(Activity::query()->where('deal_id', $deal->id)->where('action', 'deal.status_changed')->count())->toBe(7)
        ->and(OutboxMessage::query()->where('event_name', 'deal.status_changed')->count())->toBe(7)
        ->and(DB::table('audit_log')->where('table_name', 'deals')->where('row_id', $deal->id)->count())->toBeGreaterThan(8)
        ->and(DB::table('audit_log')->where('table_name', 'deal_documents')->whereIn('row_id', $deal->documents()->pluck('id'))->exists())->toBeTrue();
    Queue::assertPushed(ScanUploadedFile::class, 8);
});

it('dört rolün akış içindeki görme ve işlem sınırlarını sızıntısız uygular', function (): void {
    $users = acceptanceUsers();
    $fixture = acceptanceLead($users['marketing']);
    $foreignMarketing = User::factory()->create(['email' => 'diger@kabul.invalid']);
    $foreignMarketing->assignRole('Pazarlama');
    $foreignFixture = acceptanceLead($foreignMarketing);
    $version = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->sole()->versions()->firstOrFail();

    foreach (['called', 'interested', 'proposal_sent'] as $code) {
        app(TransitionLead::class)->handle($fixture['lead']->id, acceptanceStatus('lead', $code)->id, $users['marketing']->id);
    }
    $dealId = app(TransitionLead::class)->handle($fixture['lead']->id, acceptanceStatus('lead', 'won')->id, $users['marketing']->id, programVersionId: $version->id);
    $deal = Deal::query()->findOrFail($dealId);
    app(AssignDeal::class)->handle($deal->id, $users['project_manager']->id, acceptanceStatus('deal', 'pm_assigned')->id, $users['officer']->id);
    $document = $deal->documents()->firstOrFail();
    $scopes = app(ScopedQuery::class);

    expect($scopes->apply(Lead::query(), $users['marketing'])->whereKey($fixture['lead']->id)->exists())->toBeTrue()
        ->and($scopes->apply(Lead::query(), $users['marketing'])->whereKey($foreignFixture['lead']->id)->exists())->toBeFalse()
        ->and($users['marketing']->can('lead.manage'))->toBeTrue()
        ->and($users['marketing']->can('deal.assign'))->toBeFalse()
        ->and($users['marketing']->can('document.approve'))->toBeFalse()
        ->and($scopes->apply(Deal::query(), $users['project_manager'])->whereKey($deal->id)->exists())->toBeTrue()
        ->and(Gate::forUser($users['project_manager'])->allows('update', $deal))->toBeTrue()
        ->and(Gate::forUser($users['project_manager'])->allows('update', $document))->toBeTrue()
        ->and($users['project_manager']->can('deal.assign'))->toBeFalse()
        ->and($users['project_manager']->can('program.manage'))->toBeFalse()
        ->and($scopes->apply(Deal::query(), $users['officer'])->whereKey($deal->id)->exists())->toBeTrue()
        ->and($users['officer']->can('deal.assign'))->toBeTrue()
        ->and($users['officer']->can('document.download'))->toBeTrue()
        ->and($users['officer']->can('system.users'))->toBeFalse()
        ->and($scopes->apply(Deal::query(), $users['admin'])->whereKey($deal->id)->exists())->toBeFalse()
        ->and($users['admin']->can('system.users'))->toBeTrue()
        ->and($users['admin']->can('program.manage'))->toBeTrue()
        ->and($users['admin']->can('document.download'))->toBeFalse();

    actingAs($users['admin']);
    get(DealDetail::getUrl(['deal' => $deal->id]))->assertForbidden();
});
