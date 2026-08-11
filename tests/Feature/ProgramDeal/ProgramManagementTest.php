<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Actions\CopyProgramVersion;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('önceki sürümden şablonları bağımsız kopyalar ve açık dosyaları etkilemez', function (): void {
    $source = ProgramVersion::query()->with('docTemplates')->firstOrFail();
    $sourceTemplate = $source->docTemplates->firstOrFail();
    $actor = User::factory()->create(['email' => 'surum-kopya-aktor@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Sürüm İşletmesi', 'city' => '35']);
    $status = Status::query()->where('type', 'deal')->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $source->id,
        'reference_no' => 'WP15-SURUM-001',
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_doc_template_id' => $sourceTemplate->id,
        'source_program_version_id' => $source->id,
        'name_snapshot' => $sourceTemplate->name,
        'required_snapshot' => $sourceTemplate->is_required,
        'condition_snapshot' => $sourceTemplate->condition,
        'status' => 'to_request',
    ]);

    $target = app(CopyProgramVersion::class)->execute($source, [
        'call_period' => '2027 kurgusal çağrısı',
        'application_opens_at' => now()->addYear(),
        'application_closes_at' => now()->addYear()->addMonth(),
        'description' => 'Kurgusal kopyalama kanıtı.',
        'is_active' => true,
    ]);

    $copiedTemplate = $target->docTemplates->firstOrFail();
    $copiedTemplate->update(['name' => 'Kurgusal Değiştirilen Yeni Evrak']);

    expect($target->id)->not->toBe($source->id)
        ->and($target->docTemplates)->toHaveCount($source->docTemplates->count())
        ->and($copiedTemplate->id)->not->toBe($sourceTemplate->id)
        ->and($sourceTemplate->refresh()->name)->not->toBe('Kurgusal Değiştirilen Yeni Evrak')
        ->and($deal->refresh()->program_version_id)->toBe($source->id)
        ->and($document->refresh()->source_program_version_id)->toBe($source->id)
        ->and($document->name_snapshot)->toBe($sourceTemplate->name);
});
