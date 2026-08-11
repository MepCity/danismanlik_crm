<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Exceptions\WorkflowDeactivationBlocked;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\OrphanTransitionInspectorContract;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Workflow\SubjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{actor: User, company: Company, from: Status, to: Status, transition: Transition, deals: list<Deal>}
 */
function orphanDealFixture(int $dealCount = 2): array
{
    $actor = User::factory()->create(['email' => 'yetim-kontrol@example.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Yetim Kontrol İşletmesi',
        'city' => '06',
    ]);
    $program = Program::query()->create([
        'name' => 'Kurgusal Yetim Kontrol Programı',
        'institution' => 'other',
        'code' => 'YETIM-KONTROL',
    ]);
    $version = $program->versions()->create(['call_period' => '2099-yetim']);
    $from = Status::query()->create([
        'code' => 'musteri_onayi_bekleniyor',
        'label' => 'Müşteri onayı bekleniyor',
        'type' => 'deal',
        'color' => 'waiting',
    ]);
    $to = Status::query()->create([
        'code' => 'kuruma_gonderildi',
        'label' => 'Kuruma gönderildi',
        'type' => 'deal',
        'color' => 'info',
    ]);
    $transition = Transition::query()->create([
        'from_status_id' => $from->id,
        'to_status_id' => $to->id,
    ]);
    $deals = [];

    foreach (range(1, $dealCount) as $index) {
        $deals[] = Deal::query()->create([
            'company_id' => $company->id,
            'program_version_id' => $version->id,
            'reference_no' => 'YETIM-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'status_id' => $from->id,
            'status_changed_at' => now(),
            'opened_by_user_id' => $actor->id,
        ]);
    }

    return compact('actor', 'company', 'from', 'to', 'transition', 'deals');
}

it('returns the occupied status and exact deal count before the last exit is disabled', function (): void {
    $fixture = orphanDealFixture();

    $impact = app(OrphanTransitionInspectorContract::class)
        ->beforeTransitionDeactivation($fixture['transition']->id);

    expect($impact->hasOrphans())->toBeTrue()
        ->and($impact->subjectCount())->toBe(2)
        ->and($impact->statuses)->toHaveCount(1)
        ->and($impact->statuses[0]->statusId)->toBe($fixture['from']->id)
        ->and($impact->statuses[0]->statusLabel)->toBe('Müşteri onayı bekleniyor')
        ->and($impact->statuses[0]->subjectType)->toBe(SubjectType::Deal)
        ->and($impact->statuses[0]->subjectCount)->toBe(2);
});

it('forces the deactivation flow to block an orphaning transition', function (): void {
    app()->setLocale('tr');
    $fixture = orphanDealFixture();

    expect(fn () => app(WorkflowDeactivationService::class)
        ->deactivateTransition(
            $fixture['transition']->id,
            $fixture['actor']->id,
            'kurgusal yetim kontrolü',
        ))
        ->toThrow(
            WorkflowDeactivationBlocked::class,
            'Şu anda 2 dosya "Müşteri onayı bekleniyor" statüsünde',
        )
        ->and($fixture['transition']->refresh()->is_active)->toBeTrue();
});

it('allows transition deactivation when another active exit remains', function (): void {
    $fixture = orphanDealFixture();
    $alternative = Status::query()->create([
        'code' => 'alternatif_hedef',
        'label' => 'Alternatif hedef',
        'type' => 'deal',
        'color' => 'neutral',
    ]);
    Transition::query()->create([
        'from_status_id' => $fixture['from']->id,
        'to_status_id' => $alternative->id,
    ]);

    app(WorkflowDeactivationService::class)->deactivateTransition(
        $fixture['transition']->id,
        $fixture['actor']->id,
        'alternatif çıkış kaldığı için pasifleştirildi',
    );

    $revision = WorkflowRevision::query()->sole();
    /** @var list<array{from: string, is_active: bool}> $transitionSnapshot */
    $transitionSnapshot = $revision->snapshot['transitions'];
    $deactivatedTransition = collect($transitionSnapshot)->firstWhere(
        'from',
        'deal.'.$fixture['from']->code,
    );

    if ($deactivatedTransition === null) {
        throw new RuntimeException('Pasifleştirilen geçiş revizyon anlık görüntüsünde bulunamadı.');
    }

    expect($fixture['transition']->refresh()->is_active)->toBeFalse()
        ->and($revision->changed_by)->toBe($fixture['actor']->id)
        ->and($revision->reason)->toBe('alternatif çıkış kaldığı için pasifleştirildi')
        ->and($deactivatedTransition['is_active'])->toBeFalse();
});

it('reports both occupied status and predecessor orphaned by status deactivation', function (): void {
    $fixture = orphanDealFixture(1);
    $targetDeal = Deal::query()->create([
        'company_id' => $fixture['company']->id,
        'program_version_id' => $fixture['deals'][0]->program_version_id,
        'reference_no' => 'YETIM-HEDEF-001',
        'status_id' => $fixture['to']->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $fixture['actor']->id,
    ]);

    $impact = app(OrphanTransitionInspectorContract::class)
        ->beforeStatusDeactivation($fixture['to']->id);

    expect($targetDeal->exists)->toBeTrue()
        ->and($impact->subjectCount())->toBe(2)
        ->and(collect($impact->statuses)->pluck('statusId')->all())
        ->toContain($fixture['from']->id, $fixture['to']->id);

    expect(fn () => app(WorkflowDeactivationService::class)->deactivateStatus(
        $fixture['to']->id,
        $fixture['actor']->id,
        'kurgusal statü pasifleştirme',
    ))
        ->toThrow(WorkflowDeactivationBlocked::class)
        ->and($fixture['to']->refresh()->is_active)->toBeTrue();
});

it('counts leads through the CRM service boundary', function (): void {
    $actor = User::factory()->create(['email' => 'yetim-firsat@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Yetim Fırsat', 'city' => '35']);
    $from = Status::query()->create([
        'code' => 'firsat_bekliyor', 'label' => 'Fırsat bekliyor', 'type' => 'lead', 'color' => 'waiting',
    ]);
    $to = Status::query()->create([
        'code' => 'firsat_ilerledi', 'label' => 'Fırsat ilerledi', 'type' => 'lead', 'color' => 'info',
    ]);
    $transition = Transition::query()->create([
        'from_status_id' => $from->id,
        'to_status_id' => $to->id,
    ]);
    Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $actor->id,
        'status_id' => $from->id,
    ]);

    $impact = app(OrphanTransitionInspectorContract::class)
        ->beforeTransitionDeactivation($transition->id);

    expect($impact->subjectCount())->toBe(1)
        ->and($impact->statuses[0]->subjectType)->toBe(SubjectType::Lead);
});
