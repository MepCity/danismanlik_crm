<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function workflowStatus(string $type = 'deal', ?string $code = null): Status
{
    return Status::query()->create([
        'code' => $code ?? 'status_'.Str::lower(Str::random(10)),
        'label' => 'Kurgusal Statü',
        'type' => $type,
        'color' => 'neutral',
    ]);
}

function workflowLead(Status $status): Lead
{
    $owner = User::factory()->create();
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Fırsat Firması '.Str::random(6),
        'city' => '06',
    ]);

    return Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status_id' => $status->id,
    ]);
}

function workflowDeal(Status $status): Deal
{
    $user = User::factory()->create();
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Dosya Firması '.Str::random(6),
        'city' => '34',
    ]);
    $program = Program::query()->create([
        'name' => 'Kurgusal İş Akışı Programı '.Str::random(6),
        'institution' => 'other',
        'code' => 'WF-'.Str::upper(Str::random(8)),
    ]);
    $version = $program->versions()->create(['call_period' => '2099-'.Str::random(4)]);

    return Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'WF-D-'.Str::upper(Str::random(10)),
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $user->id,
    ]);
}

/** @param array<string, mixed> $attributes */
function workflowHistory(Status $status, array $attributes = []): StatusHistory
{
    $user = User::factory()->create();

    return StatusHistory::query()->create([
        'deal_id' => workflowDeal($status)->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'entered_at' => now(),
        'changed_by' => $user->id,
        ...$attributes,
    ]);
}

it('prevents status deletion and permits deactivation', function (): void {
    $status = workflowStatus();

    expect($status->update(['is_active' => false]))->toBeTrue()
        ->and($status->refresh()->is_active)->toBeFalse()
        ->and(fn () => $status->delete())->toThrow(QueryException::class, 'cannot be deleted');
});

it('keeps status codes immutable while allowing label changes', function (): void {
    $status = workflowStatus();

    expect($status->update(['label' => 'Kurgusal Yeni Etiket']))->toBeTrue()
        ->and(fn () => $status->update(['code' => 'changed_code']))
        ->toThrow(QueryException::class, 'immutable');
});

it('enforces status code uniqueness within each type', function (): void {
    workflowStatus('lead', 'shared_code');
    workflowStatus('deal', 'shared_code');

    expect(fn () => workflowStatus('lead', 'shared_code'))->toThrow(QueryException::class);
});

it('prevents transition deletion', function (): void {
    $transition = Transition::query()->create([
        'from_status_id' => workflowStatus('deal')->id,
        'to_status_id' => workflowStatus('deal')->id,
    ]);

    expect(fn () => $transition->delete())->toThrow(QueryException::class, 'cannot be deleted');
});

it('rejects self transitions', function (): void {
    $status = workflowStatus();

    expect(fn () => Transition::query()->create([
        'from_status_id' => $status->id,
        'to_status_id' => $status->id,
    ]))->toThrow(QueryException::class, 'transitions_distinct_statuses');
});

it('rejects duplicate transition pairs', function (): void {
    $from = workflowStatus();
    $to = workflowStatus();
    $attributes = ['from_status_id' => $from->id, 'to_status_id' => $to->id];
    Transition::query()->create($attributes);

    expect(fn () => Transition::query()->create($attributes))->toThrow(QueryException::class);
});

it('keeps workflow revisions append only', function (string $mutation): void {
    $revision = WorkflowRevision::query()->create([
        'snapshot' => ['statuses' => [], 'transitions' => []],
        'effective_from' => now(),
        'changed_by' => User::factory()->create()->id,
        'reason' => 'Kurgusal iş akışı düzenlemesi',
    ]);

    $operation = $mutation === 'update'
        ? fn () => $revision->update(['reason' => 'Kurgusal değiştirildi'])
        : fn () => $revision->delete();

    expect($operation)->toThrow(QueryException::class, 'append-only');
})->with(['update', 'delete']);

it('rejects blank workflow revision reasons', function (): void {
    expect(fn () => WorkflowRevision::query()->create([
        'snapshot' => [],
        'effective_from' => now(),
        'changed_by' => User::factory()->create()->id,
        'reason' => '   ',
    ]))->toThrow(QueryException::class, 'workflow_revisions_reason_not_blank');
});

it('allows closing an open status history row', function (): void {
    $history = workflowHistory(workflowStatus());
    $exit = now()->startOfSecond()->addMinute();

    expect($history->update(['exited_at' => $exit]))->toBeTrue()
        ->and($history->refresh()->exited_at?->equalTo($exit))->toBeTrue();
});

it('prevents status history deletion', function (): void {
    $history = workflowHistory(workflowStatus());

    expect(fn () => $history->delete())->toThrow(QueryException::class, 'cannot be deleted');
});

it('requires exactly one status history subject', function (string $case): void {
    $status = workflowStatus($case === 'deal_and_lead' ? 'deal' : 'lead');
    $user = User::factory()->create();
    $attributes = [
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'entered_at' => now(),
        'changed_by' => $user->id,
    ];

    if ($case === 'deal_and_lead') {
        $attributes['deal_id'] = workflowDeal($status)->id;
        $attributes['lead_id'] = workflowLead(workflowStatus('lead'))->id;
    }

    expect(fn () => StatusHistory::query()->create($attributes))
        ->toThrow(QueryException::class, 'status_history_exactly_one_subject');
})->with(['no subject', 'deal_and_lead']);

it('permits only one open history row per subject', function (string $subject): void {
    $status = workflowStatus($subject);
    $user = User::factory()->create();
    $subjectModel = $subject === 'deal' ? workflowDeal($status) : workflowLead($status);
    $foreignKey = "{$subject}_id";
    $attributes = [
        $foreignKey => $subjectModel->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'entered_at' => now(),
        'changed_by' => $user->id,
    ];
    StatusHistory::query()->create($attributes);

    expect(fn () => StatusHistory::query()->create([
        ...$attributes,
        'entered_at' => now()->addSecond(),
    ]))->toThrow(QueryException::class, "status_history_one_open_{$subject}");
})->with(['lead', 'deal']);

it('rejects a history exit before its entry', function (): void {
    $entered = now();

    expect(fn () => workflowHistory(workflowStatus(), [
        'entered_at' => $entered,
        'exited_at' => $entered->copy()->subSecond(),
    ]))->toThrow(QueryException::class, 'status_history_exit_not_before_entry');
});

it('enforces status foreign keys on leads and deals', function (string $subject): void {
    $status = workflowStatus($subject);
    $model = $subject === 'lead' ? workflowLead($status) : workflowDeal($status);

    expect(fn () => $model->update(['status_id' => PHP_INT_MAX]))->toThrow(QueryException::class);
})->with(['lead', 'deal']);

it('restricts deletion of a referenced status independently of delete protection', function (): void {
    $status = workflowStatus();
    workflowDeal($status);

    DB::statement('ALTER TABLE statuses DISABLE TRIGGER statuses_prevent_delete');

    try {
        expect(fn () => DB::transaction(fn () => $status->delete()))
            ->toThrow(QueryException::class, 'foreign key');
    } finally {
        DB::statement('ALTER TABLE statuses ENABLE TRIGGER statuses_prevent_delete');
    }
});
