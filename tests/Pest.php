<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Bootstrapping
|--------------------------------------------------------------------------
|
| Pest, her testte $this context olarak uses() ile belirtilen sınıfı kullanır.
| Feature testlerinde Illuminate\Foundation\Testing\TestCase gereklidir —
| bu, get(), post(), assertStatus() gibi HTTP test helper'larını sağlar.
|
*/

uses(TestCase::class)->in('Feature');

/**
 * Create an entirely fictional WP-07B deal graph for PostgreSQL contract tests.
 *
 * @return array{actor: User, deal: Deal, document: DealDocument}
 */
function wp07bDealFixture(): array
{
    $actor = User::factory()->create(['email' => 'denetim-kullanici@example.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Denetim İşletmesi',
        'city' => '06',
    ]);
    $program = Program::query()->create([
        'name' => 'Kurgusal Denetim Programı',
        'institution' => 'other',
        'code' => 'KURGUSAL-DENETIM',
    ]);
    $version = $program->versions()->create(['call_period' => '2099-denetim']);
    $status = Status::query()->create([
        'code' => 'audit_open',
        'label' => 'Kurgusal Açık',
        'type' => 'deal',
        'color' => 'neutral',
    ]);
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-AUDIT-001',
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_program_version_id' => $version->id,
        'name_snapshot' => 'Kurgusal Denetim Belgesi',
        'required_snapshot' => true,
        'status' => 'requested',
    ]);

    return compact('actor', 'deal', 'document');
}
