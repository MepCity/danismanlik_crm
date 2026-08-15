<?php

declare(strict_types=1);

use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects workflow models and casts snapshots and conditions', function (): void {
    $user = User::factory()->create(['email' => 'workflow-model@example.invalid']);
    $from = workflowStatus('deal', 'model_from');
    $to = workflowStatus('deal', 'model_to');
    $transition = Transition::query()->create([
        'from_status_id' => $from->id,
        'to_status_id' => $to->id,
        'required_permission' => 'deal.advance',
        'condition' => ['all' => [['field' => 'company.city', 'op' => 'in', 'value' => ['Ankara']]]],
    ]);
    $revision = WorkflowRevision::query()->create([
        'snapshot' => ['statuses' => [$from->code, $to->code], 'transitions' => [[$from->code, $to->code]]],
        'effective_from' => now(),
        'changed_by' => $user->id,
        'reason' => 'Kurgusal model ilişkisi',
    ]);
    $deal = workflowDeal($from);
    $history = StatusHistory::query()->create([
        'deal_id' => $deal->id,
        'status_id' => $from->id,
        'status_label_snapshot' => $from->label,
        'workflow_revision_id' => $revision->id,
        'transition_id' => $transition->id,
        'entered_at' => now(),
        'changed_by' => $user->id,
    ]);

    expect($transition->condition)->toBeArray()
        ->and($transition->fromStatus->is($from))->toBeTrue()
        ->and($transition->toStatus->is($to))->toBeTrue()
        ->and($revision->snapshot)->toBeArray()
        ->and($revision->changedBy->is($user))->toBeTrue()
        ->and($history->deal->is($deal))->toBeTrue()
        ->and($history->status->is($from))->toBeTrue()
        ->and($history->workflowRevision->is($revision))->toBeTrue()
        ->and($history->transition->is($transition))->toBeTrue()
        ->and($history->changedBy->is($user))->toBeTrue()
        ->and($deal->status->is($from))->toBeTrue()
        ->and($deal->statusHistory->modelKeys())->toBe([$history->id]);
});
