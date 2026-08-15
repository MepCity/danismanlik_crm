<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function createProgramVersion(array $attributes = []): ProgramVersion
{
    $program = Program::query()->create([
        'name' => 'Kurgusal Program '.Str::random(8),
        'institution' => 'other',
        'code' => 'TEST-'.Str::upper(Str::random(10)),
    ]);

    return $program->versions()->create([
        'call_period' => '2099-'.Str::random(4),
        ...$attributes,
    ]);
}

/** @param array<string, mixed> $attributes */
function createDeal(ProgramVersion $version, array $attributes = []): Deal
{
    $user = User::factory()->create();
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Firma '.Str::random(8),
        'city' => 'Ankara',
    ]);
    $status = Status::query()->firstOrCreate(
        ['type' => 'deal', 'code' => 'program_deal_open'],
        ['label' => 'Kurgusal Açık', 'color' => 'neutral'],
    );

    return Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-'.Str::upper(Str::random(12)),
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $user->id,
        ...$attributes,
    ]);
}

/** @param array<string, mixed> $attributes */
function createDealDocument(Deal $deal, array $attributes = []): DealDocument
{
    return DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_doc_template_id' => null,
        'source_program_version_id' => $deal->program_version_id,
        'name_snapshot' => 'Kurgusal Ek Belge',
        'description_snapshot' => 'Yalnız test için kurgusal açıklama',
        'required_snapshot' => true,
        'condition_snapshot' => ['all' => []],
        'status' => 'to_request',
        ...$attributes,
    ]);
}

it('enforces unique call periods per program', function (): void {
    $version = createProgramVersion(['call_period' => '2099-1']);

    expect(fn () => ProgramVersion::query()->create([
        'program_id' => $version->program_id,
        'call_period' => '2099-1',
    ]))->toThrow(QueryException::class);
});

it('enforces unique document template names per program version', function (): void {
    $version = createProgramVersion();
    $attributes = [
        'program_version_id' => $version->id,
        'name' => 'Kurgusal Tekil Evrak',
        'accepted_formats' => ['pdf'],
    ];
    DocTemplate::query()->create($attributes);

    expect(fn () => DocTemplate::query()->create($attributes))->toThrow(QueryException::class);
});

it('rejects an application window that closes before it opens', function (): void {
    expect(fn () => createProgramVersion([
        'application_opens_at' => '2099-02-01 09:00:00',
        'application_closes_at' => '2099-01-31 17:00:00',
    ]))->toThrow(QueryException::class, 'program_versions_application_window');
});

it('enforces unique file version numbers per deal document', function (): void {
    $document = createDealDocument(createDeal(createProgramVersion()));
    $uploader = User::factory()->create();
    $base = [
        'deal_document_id' => $document->id,
        'original_name' => 'kurgusal-belge.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 128,
        'sha256' => str_repeat('a', 64),
        'version_no' => 1,
        'uploaded_by' => $uploader->id,
    ];
    File::query()->create([...$base, 'storage_key' => (string) Str::uuid()]);

    expect(fn () => File::query()->create([
        ...$base,
        'storage_key' => (string) Str::uuid(),
    ]))->toThrow(QueryException::class);
});

it('requires every file to belong to a deal document', function (): void {
    $uploader = User::factory()->create();

    expect(fn () => File::query()->create([
        'deal_document_id' => null,
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 64,
        'sha256' => str_repeat('b', 64),
        'version_no' => 1,
        'uploaded_by' => $uploader->id,
    ]))->toThrow(QueryException::class);
});

it('rejects nonpositive file sizes and version numbers', function (array $override): void {
    $document = createDealDocument(createDeal(createProgramVersion()));
    $uploader = User::factory()->create();

    expect(fn () => File::query()->create([
        'deal_document_id' => $document->id,
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 64,
        'sha256' => str_repeat('e', 64),
        'version_no' => 1,
        'uploaded_by' => $uploader->id,
        ...$override,
    ]))->toThrow(QueryException::class);
})->with([
    'zero size' => [['size_bytes' => 0]],
    'zero version' => [['version_no' => 0]],
]);

it('rejects uncontrolled program priority and scan codes', function (string $target): void {
    if ($target === 'institution') {
        expect(fn () => Program::query()->create([
            'name' => 'Kurgusal Geçersiz Kurum',
            'institution' => 'invalid',
            'code' => 'INVALID-INSTITUTION',
        ]))->toThrow(QueryException::class);

        return;
    }

    $deal = createDeal(createProgramVersion());

    if ($target === 'priority') {
        expect(fn () => $deal->update(['priority' => 'invalid']))->toThrow(QueryException::class);

        return;
    }

    $document = createDealDocument($deal);
    $uploader = User::factory()->create();
    expect(fn () => File::query()->create([
        'deal_document_id' => $document->id,
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 64,
        'sha256' => str_repeat('f', 64),
        'version_no' => 1,
        'uploaded_by' => $uploader->id,
        'scan_result' => 'invalid',
    ]))->toThrow(QueryException::class);
})->with(['institution', 'priority', 'scan_result']);

it('accepts exactly the nine deal document statuses', function (): void {
    $deal = createDeal(createProgramVersion());
    $statuses = [
        'to_request', 'requested', 'uploaded', 'under_review', 'accepted',
        'rejected', 'new_version_expected', 'not_required', 'expired',
    ];

    foreach ($statuses as $status) {
        createDealDocument($deal, [
            'name_snapshot' => "Kurgusal {$status}",
            'status' => $status,
        ]);
    }

    expect(DealDocument::query()->pluck('status')->all())->toEqualCanonicalizing($statuses)
        ->and(fn () => createDealDocument($deal, ['status' => 'unknown']))
        ->toThrow(QueryException::class);
});

it('allows an ad hoc deal document without a source template', function (): void {
    $document = createDealDocument(createDeal(createProgramVersion()));

    expect($document->source_doc_template_id)->toBeNull();
});

it('restricts deletion across the program document and file chain', function (): void {
    $version = createProgramVersion();
    $template = DocTemplate::query()->create([
        'program_version_id' => $version->id,
        'name' => 'Kurgusal Şablon',
        'accepted_formats' => ['pdf'],
    ]);
    $deal = createDeal($version);
    $document = createDealDocument($deal, ['source_doc_template_id' => $template->id]);
    $uploader = User::factory()->create();
    File::query()->create([
        'deal_document_id' => $document->id,
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 64,
        'sha256' => str_repeat('c', 64),
        'version_no' => 1,
        'uploaded_by' => $uploader->id,
    ]);

    expect(fn () => $version->program->delete())->toThrow(QueryException::class)
        ->and(fn () => $version->delete())->toThrow(QueryException::class)
        ->and(fn () => $deal->delete())->toThrow(QueryException::class)
        ->and(fn () => $document->delete())->toThrow(QueryException::class);
});

it('enforces unique deal reference numbers while allowing repeat applications', function (): void {
    $version = createProgramVersion();
    $first = createDeal($version, ['reference_no' => 'D-REPEAT-001']);

    $second = Deal::query()->create([
        'company_id' => $first->company_id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-REPEAT-002',
        'status_id' => $first->status_id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $first->opened_by_user_id,
    ]);

    expect($second->exists)->toBeTrue()
        ->and(fn () => Deal::query()->create([
            'company_id' => $first->company_id,
            'program_version_id' => $version->id,
            'reference_no' => 'D-REPEAT-001',
            'status_id' => $first->status_id,
            'status_changed_at' => now(),
            'opened_by_user_id' => $first->opened_by_user_id,
        ]))->toThrow(QueryException::class);
});

it('requires interactions to reference exactly one real lead or deal', function (): void {
    $user = User::factory()->create();
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Görüşme Firması',
        'city' => 'İstanbul',
    ]);
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $user->id,
        'status_id' => Status::query()->create([
            'code' => 'interaction_new',
            'label' => 'Kurgusal Yeni',
            'type' => 'lead',
            'color' => 'neutral',
        ])->id,
    ]);
    $deal = createDeal(createProgramVersion());
    $attributes = [
        'user_id' => $user->id,
        'type' => 'call',
        'direction' => 'outbound',
        'purpose' => 'service',
        'occurred_at' => now(),
    ];

    expect(fn () => Interaction::query()->create([
        ...$attributes,
        'lead_id' => $lead->id,
        'deal_id' => $deal->id,
    ]))->toThrow(QueryException::class, 'interactions_exactly_one_subject')
        ->and(fn () => Interaction::query()->create([
            ...$attributes,
            'deal_id' => PHP_INT_MAX,
        ]))->toThrow(QueryException::class);
});

it('rejects an invalid call direction at the database boundary', function (): void {
    $user = User::factory()->create();
    $deal = createDeal(createProgramVersion());

    expect(fn () => Interaction::query()->create([
        'deal_id' => $deal->id,
        'user_id' => $user->id,
        'type' => 'call',
        'direction' => 'sideways',
        'purpose' => 'service',
        'occurred_at' => now(),
    ]))->toThrow(QueryException::class, 'interactions_call_context');
});
