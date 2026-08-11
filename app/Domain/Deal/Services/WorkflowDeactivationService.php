<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\Exceptions\WorkflowDeactivationBlocked;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use Illuminate\Support\Facades\DB;

final readonly class WorkflowDeactivationService
{
    public function __construct(private OrphanTransitionInspectorContract $orphans) {}

    public function deactivateTransition(int $transitionId): void
    {
        DB::transaction(function () use ($transitionId): void {
            $transition = Transition::query()->lockForUpdate()->findOrFail($transitionId);
            $impact = $this->orphans->beforeTransitionDeactivation($transition->id);

            if ($impact->hasOrphans()) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $transition->update(['is_active' => false]);
        });
    }

    public function deactivateStatus(int $statusId): void
    {
        DB::transaction(function () use ($statusId): void {
            $status = Status::query()->lockForUpdate()->findOrFail($statusId);
            $impact = $this->orphans->beforeStatusDeactivation($status->id);

            if ($impact->hasOrphans()) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $status->update(['is_active' => false]);
        });
    }
}
