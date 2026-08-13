<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Exceptions\DocumentFileRejected;
use App\Domain\Document\Exceptions\DocumentStatusRejected;
use App\Domain\Document\Jobs\ScanUploadedFile;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Document\Scanning\ScanResult;
use App\Domain\Document\Scanning\StubVirusScanner;
use App\Domain\Document\Scanning\VirusScanner;
use App\Domain\Document\Services\DealDocumentCompletion;
use App\Domain\Document\Services\DocumentAccessService;
use App\Domain\Document\Services\DocumentStatusService;
use App\Domain\Document\Services\DocumentTransaction;
use App\Domain\Document\Services\DocumentUploadService;
use App\Domain\Document\Services\ExpireDocuments;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Outbox\Models\OutboxMessage;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
    config()->set('documents.disk', 's3');
    config()->set('documents.max_size_kb', 1024);
    Queue::fake();
});

/** @return array{actor: User, pm: User, deal: Deal, document: DealDocument} */
function documentServiceFixture(bool $withValidity = false): array
{
    $actor = User::factory()->create([
        'email' => fake()->unique()->userName().'@belge.invalid',
        'data_scope' => 'own',
    ]);
    $actor->givePermissionTo(['document.upload', 'document.download', 'document.approve', 'deal.transition']);
    $pm = User::factory()->create(['email' => fake()->unique()->userName().'@pm-belge.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Belge İşletmesi '.fake()->unique()->numerify('####'),
        'city' => '06',
    ]);
    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
    $version = $program->versions()->firstOrFail();
    $status = Status::query()->where('type', 'deal')->where('code', 'collecting_documents')->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => fake()->unique()->bothify('DOC-########'),
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'pm_user_id' => $pm->id,
        'opened_by_user_id' => $actor->id,
    ]);
    $template = $withValidity
        ? DocTemplate::query()->where('program_version_id', $version->id)->where('validity_days', 30)->firstOrFail()
        : DocTemplate::query()->where('program_version_id', $version->id)->firstOrFail();
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_doc_template_id' => $template->id,
        'source_program_version_id' => $version->id,
        'name_snapshot' => $template->name,
        'required_snapshot' => true,
        'status' => 'requested',
    ]);

    return compact('actor', 'pm', 'deal', 'document');
}

function fictionalPdf(string $name = 'kurgusal-rapor.pdf', string $suffix = ''): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nKurgusal test belgesi {$suffix}\n%%EOF");
}

it('rejects an unlisted extension and a forged pdf by inspecting content', function (): void {
    $fixture = documentServiceFixture();
    $service = app(DocumentUploadService::class);

    expect(fn () => $service->upload($fixture['document']->id, UploadedFile::fake()->createWithContent('zararsiz.exe', 'test'), $fixture['actor']->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.extension'))
        ->and(fn () => $service->upload($fixture['document']->id, UploadedFile::fake()->createWithContent('sahte.pdf', 'PDF olmayan içerik'), $fixture['actor']->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.mime'));
});

it('rejects files over the configurable size limit', function (): void {
    config()->set('documents.max_size_kb', 1);
    $fixture = documentServiceFixture();

    expect(fn () => app(DocumentUploadService::class)->upload(
        $fixture['document']->id,
        UploadedFile::fake()->createWithContent('buyuk.pdf', '%PDF-'.str_repeat('x', 2048)),
        $fixture['actor']->id,
    ))->toThrow(DocumentFileRejected::class, trans('documents.errors.too_large'));
});

it('uploads opaque immutable versions to real object storage and sets milestones', function (): void {
    Carbon::setTestNow('2026-08-11 10:00:00');
    $fixture = documentServiceFixture(withValidity: true);
    $service = app(DocumentUploadService::class);
    $first = $service->upload($fixture['document']->id, fictionalPdf(suffix: 'v1'), $fixture['actor']->id);
    $second = $service->upload($fixture['document']->id, fictionalPdf('findeks-yeni.pdf', 'v2'), $fixture['actor']->id);

    expect($first->file->version_no)->toBe(1)
        ->and($second->file->version_no)->toBe(2)
        ->and(File::query()->where('deal_document_id', $fixture['document']->id)->count())->toBe(2)
        ->and(Str::isUuid($first->file->storage_key))->toBeTrue()
        ->and($first->file->storage_key)->not->toContain((string) $fixture['deal']->id, 'kurgusal-rapor')
        ->and(Storage::disk('s3')->exists($first->file->storage_key))->toBeTrue()
        ->and(Storage::disk('s3')->get($first->file->storage_key))->toContain('v1')
        ->and($fixture['deal']->refresh()->first_document_received_at?->toDateTimeString())->toBe('2026-08-11 10:00:00')
        ->and($fixture['document']->refresh()->validity_expires_at?->toDateTimeString())->toBe('2026-09-10 10:00:00');
    Queue::assertPushed(ScanUploadedFile::class, 2);
});

it('rejects duplicate content without creating a version or object', function (): void {
    $fixture = documentServiceFixture();
    $service = app(DocumentUploadService::class);
    $service->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id);

    expect(fn () => $service->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.duplicate'))
        ->and(File::query()->where('deal_document_id', $fixture['document']->id)->count())->toBe(1);
});

it('enforces unique document version numbers in PostgreSQL', function (): void {
    $fixture = documentServiceFixture();
    $file = app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id)->file;

    expect(fn () => DB::transaction(fn () => File::query()->create([
        'deal_document_id' => $file->deal_document_id,
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal-ikinci.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
        'sha256' => str_repeat('a', 64),
        'version_no' => 1,
        'uploaded_by' => $fixture['actor']->id,
    ])))->toThrow(QueryException::class);
});

it('records access request and actual proxy download as separate events', function (): void {
    $fixture = documentServiceFixture();
    $file = app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id)->file;
    $file->update(['scan_result' => 'clean']);
    $access = app(DocumentAccessService::class);
    $url = $access->temporaryUrl($file->id, $fixture['actor']->id);

    expect(Activity::query()->where('action', 'document.access_requested')->count())->toBe(1)
        ->and(Activity::query()->where('action', 'document.downloaded')->count())->toBe(0)
        ->and(OutboxMessage::query()->where('event_name', 'document.access_requested')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('event_name', 'document.downloaded')->count())->toBe(0);

    actingAs($fixture['actor']);
    $response = get($url);
    $response->assertOk();
    expect($response->streamedContent())->toContain('Kurgusal test belgesi')
        ->and(Activity::query()->where('action', 'document.downloaded')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('event_name', 'document.downloaded')->count())->toBe(1);
});

it('denies link generation without permission and denies infected or pending files', function (): void {
    $fixture = documentServiceFixture();
    $file = app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id)->file;
    $unauthorized = User::factory()->create(['email' => 'yetkisiz-belge@example.invalid']);

    expect(fn () => app(DocumentAccessService::class)->temporaryUrl($file->id, $unauthorized->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.forbidden'))
        ->and(fn () => app(DocumentAccessService::class)->temporaryUrl($file->id, $fixture['actor']->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.unavailable'));

    $file->update(['scan_result' => 'infected']);
    expect(fn () => app(DocumentAccessService::class)->download($file->id, $fixture['actor']->id))
        ->toThrow(DocumentFileRejected::class, trans('documents.errors.unavailable'));
});

it('requires reasons for rejection and new-version decisions', function (string $target): void {
    $fixture = documentServiceFixture();
    $fixture['document']->update(['status' => 'under_review']);

    expect(fn () => app(DocumentStatusService::class)->decide($fixture['document']->id, $target, '  ', $fixture['actor']->id))
        ->toThrow(DocumentStatusRejected::class, trans('documents.errors.reason_required'));
})->with(['rejected', 'new_version_expected']);

it('denies document review without the approval permission', function (): void {
    $fixture = documentServiceFixture();
    $fixture['document']->update(['status' => 'uploaded']);
    $unauthorized = User::factory()->create(['email' => 'yetkisiz-inceleme@example.invalid']);

    expect(fn () => app(DocumentStatusService::class)->startReview($fixture['document']->id, $unauthorized->id))
        ->toThrow(DocumentStatusRejected::class, trans('documents.errors.forbidden'));
});

it('marks infected scans rejected and makes them unavailable', function (): void {
    $fixture = documentServiceFixture();
    $file = app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id)->file;
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ScanResult
        {
            return ScanResult::Infected;
        }
    });

    (new ScanUploadedFile($file->id))->handle(
        app(VirusScanner::class),
        app(DocumentTransaction::class),
        app(ActivityRecorder::class),
        app(DealDocumentCompletion::class),
    );

    expect($file->refresh()->scan_result)->toBe('infected')
        ->and($fixture['document']->refresh()->status)->toBe('rejected');
});

it('refuses to run the clean stub in production', function (): void {
    expect(fn () => (new StubVirusScanner('production'))->scan('/tmp/kurgusal'))
        ->toThrow(RuntimeException::class, trans('documents.errors.stub_production'));
});

it('expires validity, clears completion, notifies the pm and blocks the workflow transition', function (): void {
    Carbon::setTestNow('2026-08-11 10:00:00');
    $fixture = documentServiceFixture(withValidity: true);
    $file = app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id)->file;
    $file->update(['scan_result' => 'clean']);
    $fixture['document']->update(['status' => 'accepted']);
    app(DealDocumentCompletion::class)->refresh($fixture['deal']->id);
    StatusHistory::query()->create([
        'deal_id' => $fixture['deal']->id,
        'status_id' => $fixture['deal']->status_id,
        'status_label_snapshot' => 'Belgeler toplanıyor',
        'workflow_revision_id' => WorkflowRevision::query()->firstOrFail()->id,
        'entered_at' => now(),
        'changed_by' => $fixture['actor']->id,
    ]);

    expect($fixture['deal']->refresh()->all_required_accepted_at)->not->toBeNull();
    Carbon::setTestNow('2026-09-10 10:00:01');
    expect(app(ExpireDocuments::class)->run())->toBe(1)
        ->and($fixture['document']->refresh()->status)->toBe('expired')
        ->and($fixture['deal']->refresh()->all_required_accepted_at)->toBeNull()
        ->and(Notification::query()->where('user_id', $fixture['pm']->id)->where('type', 'document.expired')->exists())->toBeTrue();

    $target = Status::query()->where('type', 'deal')->where('code', 'preparing_application')->firstOrFail();
    expect(fn () => app(StatusMachineContract::class)->transition(new StatusTransition(
        SubjectType::Deal,
        $fixture['deal']->id,
        $target->id,
        $fixture['actor']->id,
    )))->toThrow(StatusTransitionRejected::class);
});

it('compensates object storage when the database transaction fails', function (): void {
    $fixture = documentServiceFixture();
    $before = Storage::disk('s3')->allFiles();
    app()->instance(ActivityRecorder::class, new class implements ActivityRecorder
    {
        public function record(string $action, array $payload, ?int $actorId = null, ?int $leadId = null, ?int $dealId = null, ?int $dealDocumentId = null, ?Carbon $occurredAt = null, ?string $defaultSource = null, ?int $companyId = null): void
        {
            throw new RuntimeException('Kurgusal atomiklik hatası');
        }

        public function recordStatusChanged(SubjectType $subjectType, int $subjectId, int $actorId, array $fromStatus, array $toStatus, Carbon $occurredAt): void {}
    });

    expect(fn () => app(DocumentUploadService::class)->upload($fixture['document']->id, fictionalPdf(), $fixture['actor']->id))
        ->toThrow(RuntimeException::class, 'Kurgusal atomiklik hatası')
        ->and(File::query()->where('deal_document_id', $fixture['document']->id)->exists())->toBeFalse()
        ->and(Storage::disk('s3')->allFiles())->toEqualCanonicalizing($before);
});
