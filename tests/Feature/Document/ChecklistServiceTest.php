<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Document\Models\File;
use App\Domain\Document\Services\AdHocDocumentService;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Document\Services\DocumentRequestService;
use App\Domain\Document\Services\DocumentRequirementDecisionService;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Conditions\Exceptions\UnknownConditionOperator;
use App\Support\Events\DomainEvent;
use App\Support\Outbox\DatabaseOutboxWriter;
use App\Support\Outbox\Models\OutboxMessage;
use App\Support\Outbox\OutboxWriter;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array{deal: Deal, company: Company, actor: User, pm: User} */
function checklistFixture(string $city = '06', string $amount = '4000000.00'): array
{
    $actor = User::factory()->create(['email' => fake()->unique()->userName().'@acilis.invalid']);
    $pm = User::factory()->create(['email' => fake()->unique()->userName().'@pm.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Checklist İşletmesi '.fake()->unique()->numerify('####'),
        'city' => $city,
    ]);
    $version = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail()
        ->versions()->where('call_period', '2026 çağrısı')->firstOrFail();
    $status = Status::query()->where('type', 'deal')->where('code', 'collecting_documents')->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => fake()->unique()->bothify('CHK-########'),
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'pm_user_id' => $pm->id,
        'opened_by_user_id' => $actor->id,
        'requested_amount' => $amount,
    ]);
    app(ChecklistGeneratorContract::class)->generate($deal->id, $actor->id);

    return compact('deal', 'company', 'actor', 'pm');
}

it('creates only five unconditional documents when both conditions fail', function (): void {
    $fixture = checklistFixture();
    $documents = $fixture['deal']->documents()->orderBy('id')->get();

    expect($documents)->toHaveCount(5)
        ->and(DocTemplate::query()->where('program_version_id', $fixture['deal']->program_version_id)->count())->toBe(7)
        ->and($documents->pluck('name_snapshot')->all())->not->toContain('Hasar Durumu Belgesi', 'Fizibilite Raporu')
        ->and($documents->pluck('status')->unique()->all())->toBe(['to_request'])
        ->and(Activity::query()->where('deal_id', $fixture['deal']->id)->where('action', 'deal.checklist_generated')->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('event_name', 'deal.checklist_generated')->exists())->toBeTrue();
});

it('creates the earthquake document for one of the configured eleven cities', function (): void {
    $fixture = checklistFixture(city: '31');

    expect($fixture['deal']->documents()->where('name_snapshot', 'Hasar Durumu Belgesi')->exists())->toBeTrue()
        ->and($fixture['deal']->documents()->count())->toBe(6);
});

it('creates the feasibility report above the configured amount threshold', function (): void {
    $fixture = checklistFixture(amount: '5000000.01');

    expect($fixture['deal']->documents()->where('name_snapshot', 'Fizibilite Raporu')->exists())->toBeTrue()
        ->and($fixture['deal']->documents()->count())->toBe(6);
});

it('is idempotent and keeps snapshot and source references together', function (): void {
    $fixture = checklistFixture();
    $before = $fixture['deal']->documents()->count();

    $result = app(ChecklistGeneratorContract::class)->generate($fixture['deal']->id, $fixture['actor']->id);
    $document = $fixture['deal']->documents()->whereNotNull('source_doc_template_id')->firstOrFail();
    $template = $document->sourceDocTemplate()->firstOrFail();

    expect($result->createdDocumentIds)->toBe([])
        ->and($fixture['deal']->documents()->count())->toBe($before)
        ->and($document->source_program_version_id)->toBe($fixture['deal']->program_version_id)
        ->and($document->name_snapshot)->toBe($template->name)
        ->and($document->description_snapshot)->toBe($template->description)
        ->and($document->required_snapshot)->toBe($template->is_required)
        ->and($document->condition_snapshot)->toBe($template->condition);
});

it('adds a newly matching document and notifies the project manager', function (): void {
    $fixture = checklistFixture();

    $fixture['company']->update(['city' => '31']);

    $document = $fixture['deal']->documents()->where('name_snapshot', 'Hasar Durumu Belgesi')->sole();
    $notification = Notification::query()->where('user_id', $fixture['pm']->id)->sole();

    expect($document->condition_matches)->toBeTrue()
        ->and($notification->deal_id)->toBe($fixture['deal']->id)
        ->and($notification->body)->toBe('Bu dosyaya 1 yeni zorunlu evrak eklendi: Hasar Durumu Belgesi.')
        ->and(OutboxMessage::query()->where('event_name', 'deal.checklist_reevaluated')->exists())->toBeTrue();
});

it('never deletes a document or its uploaded file when its condition stops matching', function (): void {
    $fixture = checklistFixture(amount: '6000000.00');
    $document = $fixture['deal']->documents()->where('name_snapshot', 'Fizibilite Raporu')->sole();
    $file = File::query()->create([
        'deal_document_id' => $document->id,
        'storage_key' => fake()->uuid(),
        'original_name' => 'kurgusal-fizibilite.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'sha256' => str_repeat('a', 64),
        'version_no' => 1,
        'uploaded_by' => $fixture['pm']->id,
        'scan_result' => 'clean',
    ]);

    $fixture['deal']->update(['requested_amount' => '4000000.00']);

    $suggestion = DocumentRequirementSuggestion::query()->where('deal_document_id', $document->id)->sole();
    expect($document->refresh()->exists)->toBeTrue()
        ->and($document->condition_matches)->toBeFalse()
        ->and($document->status)->not->toBe('not_required')
        ->and($suggestion->status)->toBe('pending')
        ->and($file->refresh()->deal_document_id)->toBe($document->id)
        ->and(Activity::query()->where('action', 'document.requirement_suggested')->sole()->source)->toBe('automation');

    app(DocumentRequirementDecisionService::class)->decide($suggestion->id, $fixture['pm']->id, true);

    expect($document->refresh()->status)->toBe('not_required')
        ->and($document->files()->whereKey($file->id)->exists())->toBeTrue()
        ->and($suggestion->refresh()->status)->toBe('accepted')
        ->and(Activity::query()->where('action', 'document.requirement_decided')->sole()->source)->toBe('user');

    $auditSources = DB::table('audit_log')
        ->where('table_name', 'document_requirement_suggestions')
        ->where('row_id', $suggestion->id)
        ->orderBy('created_at')
        ->pluck('source')
        ->all();

    expect($auditSources)->toContain('automation', 'user');
});

it('keeps the document required when the project manager rejects the suggestion', function (): void {
    $fixture = checklistFixture(amount: '6000000.00');
    $document = $fixture['deal']->documents()->where('name_snapshot', 'Fizibilite Raporu')->sole();
    $fixture['deal']->update(['requested_amount' => '4000000.00']);
    $suggestion = $document->requirementSuggestions()->sole();

    app(DocumentRequirementDecisionService::class)->decide($suggestion->id, $fixture['pm']->id, false);

    expect($suggestion->refresh()->status)->toBe('rejected')
        ->and($document->refresh()->status)->toBe('to_request')
        ->and($document->required_snapshot)->toBeFalse();
});

it('rejects deleting suggestions and creating duplicate pending decisions at database level', function (): void {
    $fixture = checklistFixture(amount: '6000000.00');
    $document = $fixture['deal']->documents()->where('name_snapshot', 'Fizibilite Raporu')->sole();
    $fixture['deal']->update(['requested_amount' => '4000000.00']);
    $suggestion = $document->requirementSuggestions()->sole();

    expect(fn () => DB::transaction(
        fn () => DB::table('document_requirement_suggestions')->where('id', $suggestion->id)->delete(),
    ))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(
            fn () => DocumentRequirementSuggestion::query()->create([
                'deal_document_id' => $document->id,
                'reason' => 'condition_no_longer_matches',
                'reason_parameters' => ['document' => $document->name_snapshot],
            ]),
        ))->toThrow(QueryException::class)
        ->and($suggestion->fresh())->not->toBeNull();
});

it('creates an ad hoc requirement without a template source', function (): void {
    $fixture = checklistFixture();

    $document = app(AdHocDocumentService::class)->create(
        $fixture['deal']->id,
        $fixture['pm']->id,
        'Kurgusal Kurum Ek Yazısı',
        'Yalnız bu dosya için istenen ek yazı.',
        true,
    );

    expect($document->source_doc_template_id)->toBeNull()
        ->and($document->source_program_version_id)->toBe($fixture['deal']->program_version_id)
        ->and($document->required_snapshot)->toBeTrue()
        ->and($document->status)->toBe('to_request')
        ->and(Activity::query()->where('action', 'document.ad_hoc_created')->where('deal_document_id', $document->id)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('event_name', 'deal.ad_hoc_document_created')->exists())->toBeTrue();
});

it('sets the first document request timestamp when rows become requested', function (): void {
    Carbon::setTestNow('2099-04-05 10:30:00');
    $fixture = checklistFixture();
    $ids = $fixture['deal']->documents()->limit(2)->pluck('id')->all();

    app(DocumentRequestService::class)->markRequested($ids, $fixture['pm']->id);

    expect($fixture['deal']->refresh()->document_requested_at?->toDateTimeString())->toBe('2099-04-05 10:30:00')
        ->and(DealDocument::query()->whereKey($ids)->where('status', 'requested')->count())->toBe(2)
        ->and(DealDocument::query()->whereKey($ids)->whereNotNull('requested_at')->count())->toBe(2);

    Carbon::setTestNow('2099-04-06 09:00:00');
    $nextId = (int) $fixture['deal']->documents()->where('status', 'to_request')->value('id');
    app(DocumentRequestService::class)->markRequested([$nextId], $fixture['pm']->id);

    expect($fixture['deal']->refresh()->document_requested_at?->toDateTimeString())->toBe('2099-04-05 10:30:00');
});

it('surfaces an unknown condition operator instead of silently skipping it', function (): void {
    $fixture = checklistFixture();
    DocTemplate::query()->create([
        'program_version_id' => $fixture['deal']->program_version_id,
        'name' => 'Kurgusal Bilinmeyen Koşul Belgesi',
        'is_required' => true,
        'condition' => ['all' => [['field' => 'company.city', 'op' => 'mystery', 'value' => ['06']]]],
        'accepted_formats' => ['pdf'],
        'sort_order' => 99,
    ]);

    expect(fn () => app(ChecklistGeneratorContract::class)->generate($fixture['deal']->id, $fixture['actor']->id))
        ->toThrow(UnknownConditionOperator::class, 'Bilinmeyen koşul operatörü: mystery.');
});

it('rolls back every checklist effect when the outbox write fails', function (): void {
    $actor = User::factory()->create(['email' => 'atomik-acilis@kurgusal.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Atomik İşletme', 'city' => '06']);
    $program = Program::query()->create(['name' => 'Kurgusal Atomik Program', 'institution' => 'other', 'code' => 'ATOMIK-TEST']);
    $version = $program->versions()->create(['call_period' => '2099 çağrısı']);
    DocTemplate::query()->create([
        'program_version_id' => $version->id,
        'name' => 'Kurgusal Atomik Belge',
        'is_required' => true,
        'accepted_formats' => ['pdf'],
    ]);
    $status = Status::query()->where('type', 'deal')->firstOrFail();
    $deal = Deal::withoutEvents(fn (): Deal => Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'ATOMIK-2099-001',
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
    ]));

    app()->instance(OutboxWriter::class, new class implements OutboxWriter
    {
        public function write(DomainEvent $event): void
        {
            (new DatabaseOutboxWriter)->write($event);
            throw new RuntimeException('Kurgusal outbox hatası');
        }
    });

    expect(fn () => app(ChecklistGeneratorContract::class)->generate($deal->id, $actor->id))
        ->toThrow(RuntimeException::class, 'Kurgusal outbox hatası')
        ->and($deal->documents()->count())->toBe(0)
        ->and(Activity::query()->where('deal_id', $deal->id)->count())->toBe(0)
        ->and(OutboxMessage::query()->where('event_name', 'deal.checklist_generated')->count())->toBe(0);
});
