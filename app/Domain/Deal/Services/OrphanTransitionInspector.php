<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Crm\Services\LeadStatusOccupancyReader;
use App\Domain\Deal\DTO\OrphanedStatus;
use App\Domain\Deal\DTO\OrphanImpact;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Support\Workflow\SubjectType;

final readonly class OrphanTransitionInspector implements OrphanTransitionInspectorContract
{
    public function __construct(private LeadStatusOccupancyReader $leadOccupancy) {}

    public function beforeTransitionDeactivation(int $transitionId): OrphanImpact
    {
        $transition = Transition::query()->findOrFail($transitionId);

        if (! $transition->is_active) {
            return new OrphanImpact([]);
        }

        $status = Status::query()->findOrFail($transition->from_status_id);

        if ($status->is_terminal || $this->activeExitCount($status->id, $transition->id) > 0) {
            return new OrphanImpact([]);
        }

        return new OrphanImpact($this->occupiedStatus($status));
    }

    public function beforeStatusDeactivation(int $statusId): OrphanImpact
    {
        $status = Status::query()->findOrFail($statusId);

        if (! $status->is_active) {
            return new OrphanImpact([]);
        }

        $candidateIds = Transition::query()
            ->where('to_status_id', $status->id)
            ->where('is_active', true)
            ->pluck('from_status_id')
            ->push($status->id)
            ->unique();
        $orphaned = [];

        foreach (Status::query()->whereKey($candidateIds)->get() as $candidate) {
            $isDeactivatedStatus = $candidate->id === $status->id;
            $losesLastExit = ! $candidate->is_terminal
                && $this->activeExitCount($candidate->id, null, $status->id) === 0;

            if ($isDeactivatedStatus || $losesLastExit) {
                $orphaned = [...$orphaned, ...$this->occupiedStatus($candidate)];
            }
        }

        return new OrphanImpact($orphaned);
    }

    private function activeExitCount(
        int $fromStatusId,
        ?int $excludedTransitionId = null,
        ?int $excludedStatusId = null,
    ): int {
        return Transition::query()
            ->where('from_status_id', $fromStatusId)
            ->where('is_active', true)
            ->when($excludedTransitionId !== null, fn ($query) => $query->where('id', '<>', $excludedTransitionId))
            ->when($excludedStatusId !== null, fn ($query) => $query->where('to_status_id', '<>', $excludedStatusId))
            ->whereHas('toStatus', static fn ($query) => $query->where('is_active', true))
            ->count();
    }

    /** @return list<OrphanedStatus> */
    private function occupiedStatus(Status $status): array
    {
        $type = SubjectType::from($status->type);
        $count = match ($type) {
            SubjectType::Deal => Deal::query()->where('status_id', $status->id)->count(),
            SubjectType::Lead => $this->leadOccupancy->count($status->id),
        };

        return $count === 0 ? [] : [new OrphanedStatus(
            $status->id,
            $status->label,
            $type,
            $count,
        )];
    }
}
