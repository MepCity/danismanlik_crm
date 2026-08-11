<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('connects program deal document and file models and casts structured values', function (): void {
    $user = User::factory()->create(['email' => 'model-chain@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Model A.Ş.', 'city' => '35']);
    $program = Program::query()->create([
        'name' => 'Kurgusal Model Programı',
        'institution' => 'other',
        'code' => 'KURGUSAL-MODEL',
    ]);
    $version = $program->versions()->create([
        'call_period' => '2099-model',
        'application_opens_at' => now(),
    ]);
    $template = $version->docTemplates()->create([
        'name' => 'Kurgusal Model Evrakı',
        'condition' => ['all' => [['field' => 'company.city', 'op' => 'in', 'value' => ['35']]]],
        'accepted_formats' => ['pdf', 'xlsx'],
    ]);
    $status = Status::query()->create([
        'code' => 'program_model_open',
        'label' => 'Kurgusal Açık',
        'type' => 'deal',
        'color' => 'neutral',
    ]);
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-MODEL-001',
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $user->id,
        'requested_amount' => '125000.50',
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_doc_template_id' => $template->id,
        'source_program_version_id' => $version->id,
        'name_snapshot' => $template->name,
        'required_snapshot' => true,
        'condition_snapshot' => $template->condition,
        'status' => 'requested',
    ]);
    $file = $document->files()->create([
        'storage_key' => (string) Str::uuid(),
        'original_name' => 'kurgusal-model.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 256,
        'sha256' => str_repeat('d', 64),
        'version_no' => 1,
        'uploaded_by' => $user->id,
    ]);
    $file->refresh();

    expect($program->versions->modelKeys())->toBe([$version->id])
        ->and($version->program->is($program))->toBeTrue()
        ->and($version->docTemplates->modelKeys())->toBe([$template->id])
        ->and($template->condition)->toBeArray()
        ->and($template->accepted_formats)->toBe(['pdf', 'xlsx'])
        ->and($deal->company->is($company))->toBeTrue()
        ->and($deal->programVersion->is($version))->toBeTrue()
        ->and($deal->documents->modelKeys())->toBe([$document->id])
        ->and($document->deal->is($deal))->toBeTrue()
        ->and($document->sourceDocTemplate->is($template))->toBeTrue()
        ->and($document->condition_snapshot)->toBeArray()
        ->and($document->files->modelKeys())->toBe([$file->id])
        ->and($file->dealDocument->is($document))->toBeTrue()
        ->and($file->uploadedBy->is($user))->toBeTrue()
        ->and($file->size_bytes)->toBe(256)
        ->and($file->is_deleted)->toBeFalse();
});
