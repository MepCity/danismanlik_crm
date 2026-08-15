<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Exceptions\WorkflowDeactivationBlocked;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('çıkışsız bırakacak pasifleştirmeyi toplu geçiş hedefi olmadan kaydetmez', function (): void {
    $fixture = workflowManagementFixture(1);
    $revisionCount = WorkflowRevision::query()->count();

    expect(fn () => app(WorkflowDeactivationService::class)->deactivateTransition(
        $fixture['transition']->id,
        $fixture['actor']->id,
        'Kurgusal geçiş düzenlemesi',
    ))->toThrow(WorkflowDeactivationBlocked::class);

    expect($fixture['transition']->refresh()->is_active)->toBeTrue()
        ->and(WorkflowRevision::query()->count())->toBe($revisionCount)
        ->and($fixture['deals'][0]->refresh()->status_id)->toBe($fixture['source']->id);
});

it('kontrollü toplu geçişi tek olayla kaydeder ve iş akışı revizyonu oluşturur', function (): void {
    $fixture = workflowManagementFixture(2);
    $revisionCount = WorkflowRevision::query()->count();

    app(WorkflowDeactivationService::class)->deactivateTransition(
        $fixture['transition']->id,
        $fixture['actor']->id,
        'Kurgusal toplu geçiş gerekçesi',
        $fixture['target']->id,
    );

    $revision = WorkflowRevision::query()->latest('id')->firstOrFail();

    expect($fixture['transition']->refresh()->is_active)->toBeFalse()
        ->and(WorkflowRevision::query()->count())->toBe($revisionCount + 1)
        ->and(data_get($revision->snapshot, 'bulk_transition.subject_count'))->toBe(2)
        ->and(Activity::query()->where('action', 'workflow.bulk_transition')->count())->toBe(1)
        ->and(Deal::query()->whereIn('id', collect($fixture['deals'])->pluck('id'))->where('status_id', $fixture['target']->id)->count())->toBe(2)
        ->and(StatusHistory::query()->whereIn('deal_id', collect($fixture['deals'])->pluck('id'))->where('status_id', $fixture['target']->id)->count())->toBe(2);
});

it('statü ve geçiş düzenlemesini gerekçesiz revizyonlayamaz', function (): void {
    $actor = User::factory()->create(['email' => 'revizyon-aktor@example.invalid']);
    $status = Status::query()->where('type', 'deal')->firstOrFail();

    expect(fn () => app(WorkflowDeactivationService::class)->updateStatus(
        $status,
        ['label' => 'Kurgusal Etiket'],
        $actor->id,
        ' ',
    ))->toThrow(InvalidArgumentException::class)
        ->and($status->refresh()->label)->not->toBe('Kurgusal Etiket');
});

/** @return array{actor: User, source: Status, target: Status, transition: Transition, deals: list<Deal>} */
function workflowManagementFixture(int $dealCount): array
{
    $actor = User::factory()->create(['email' => 'toplu-gecis-aktor@example.invalid']);
    $source = Status::query()->where('type', 'deal')->where('code', 'collecting_documents')->firstOrFail();
    $target = Status::query()->where('type', 'deal')->where('code', 'preparing_application')->firstOrFail();
    $transition = Transition::query()->where('from_status_id', $source->id)->where('to_status_id', $target->id)->firstOrFail();
    $company = Company::query()->create(['legal_name' => 'Kurgusal Toplu Geçiş İşletmesi', 'city' => 'Ankara']);
    $version = ProgramVersion::query()->firstOrFail();
    $revision = WorkflowRevision::query()->latest('effective_from')->firstOrFail();
    $deals = [];

    foreach (range(1, $dealCount) as $index) {
        $deal = Deal::query()->create([
            'company_id' => $company->id,
            'program_version_id' => $version->id,
            'reference_no' => 'WP15-TOPLU-'.$index,
            'status_id' => $source->id,
            'status_changed_at' => now(),
            'opened_by_user_id' => $actor->id,
        ]);
        StatusHistory::query()->create([
            'deal_id' => $deal->id,
            'status_id' => $source->id,
            'status_label_snapshot' => $source->label,
            'workflow_revision_id' => $revision->id,
            'entered_at' => now(),
            'changed_by' => $actor->id,
        ]);
        $deals[] = $deal;
    }

    return compact('actor', 'source', 'target', 'transition', 'deals');
}
