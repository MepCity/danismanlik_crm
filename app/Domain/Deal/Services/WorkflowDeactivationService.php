<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\Exceptions\WorkflowDeactivationBlocked;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use Illuminate\Support\Facades\DB;

final readonly class WorkflowDeactivationService
{
    public function __construct(private OrphanTransitionInspectorContract $orphans) {}

    public function deactivateTransition(int $transitionId, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($transitionId, $actorId, $reason): void {
            $transition = Transition::query()->lockForUpdate()->findOrFail($transitionId);

            if (! $transition->is_active) {
                return;
            }

            $impact = $this->orphans->beforeTransitionDeactivation($transition->id);

            if ($impact->hasOrphans()) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $transition->update(['is_active' => false]);
            $this->recordRevision($actorId, $reason);
        });
    }

    public function deactivateStatus(int $statusId, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($statusId, $actorId, $reason): void {
            $status = Status::query()->lockForUpdate()->findOrFail($statusId);

            if (! $status->is_active) {
                return;
            }

            $impact = $this->orphans->beforeStatusDeactivation($status->id);

            if ($impact->hasOrphans()) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $status->update(['is_active' => false]);
            $this->recordRevision($actorId, $reason);
        });
    }

    private function recordRevision(int $actorId, string $reason): void
    {
        $statuses = Status::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (Status $status): array => [
                'type' => $status->type,
                'code' => $status->code,
                'label' => $status->label,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
                'is_terminal' => $status->is_terminal,
                'is_active' => $status->is_active,
            ])->all();
        $transitions = Transition::query()
            ->with(['fromStatus', 'toStatus'])
            ->orderBy('id')
            ->get()
            ->map(static fn (Transition $transition): array => [
                'from' => $transition->fromStatus->type.'.'.$transition->fromStatus->code,
                'to' => $transition->toStatus->type.'.'.$transition->toStatus->code,
                'required_permission' => $transition->required_permission,
                'condition' => $transition->condition,
                'is_active' => $transition->is_active,
            ])->all();

        WorkflowRevision::query()->create([
            'snapshot' => compact('statuses', 'transitions'),
            'effective_from' => now(),
            'changed_by' => $actorId,
            'reason' => $reason,
        ]);
    }
}
