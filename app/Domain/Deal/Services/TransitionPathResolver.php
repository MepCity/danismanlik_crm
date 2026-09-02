<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Crm\Services\LeadWorkflowSubjectGateway;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubjectGateway;
use Illuminate\Database\Eloquent\Collection;

final readonly class TransitionPathResolver implements TransitionPathResolverContract
{
    public function __construct(
        private DealWorkflowSubjectGateway $deals,
        private LeadWorkflowSubjectGateway $leads,
        private TransitionGuard $guard,
    ) {}

    /**
     * @return list<int>
     */
    public function findShortestPath(
        SubjectType $subjectType,
        int $subjectId,
        int $targetStatusId,
        int $actorId,
    ): array {
        $subject = $this->gateway($subjectType)->lock($subjectId);
        $startStatus = Status::query()->findOrFail($subject->statusId);
        $targetStatus = Status::query()->findOrFail($targetStatusId);

        if ($startStatus->is_terminal) {
            throw StatusTransitionRejected::terminal($startStatus->label);
        }

        if (! $targetStatus->is_active || $targetStatus->type !== $subject->type->value) {
            throw StatusTransitionRejected::targetUnavailable();
        }

        if ($startStatus->id === $targetStatus->id) {
            return [$startStatus->id];
        }

        /** @var Collection<int, Transition> $transitions */
        $transitions = Transition::query()
            ->where('is_active', true)
            ->whereHas('fromStatus', fn ($q) => $q->where('type', $subject->type->value)->where('is_active', true))
            ->whereHas('toStatus', fn ($q) => $q->where('type', $subject->type->value)->where('is_active', true))
            ->with(['fromStatus', 'toStatus'])
            ->get();

        /** @var array<int, list<Transition>> $grouped */
        $grouped = [];
        foreach ($transitions as $transition) {
            $grouped[$transition->from_status_id][] = $transition;
        }

        foreach ($grouped as $fromId => $list) {
            usort($grouped[$fromId], function (Transition $a, Transition $b): int {
                $sortOrderA = $a->toStatus->sort_order;
                $sortOrderB = $b->toStatus->sort_order;

                if ($sortOrderA !== $sortOrderB) {
                    return $sortOrderA <=> $sortOrderB;
                }

                return $a->id <=> $b->id;
            });
        }

        /** @var list<list<int>> $queue */
        $queue = [[$startStatus->id]];
        $visited = [$startStatus->id => true];

        while (! empty($queue)) {
            $currentPath = array_shift($queue);
            $lastStatusId = end($currentPath);

            foreach ($grouped[$lastStatusId] ?? [] as $transition) {
                if (! $this->guard->isEligible($transition, $actorId, $subject)) {
                    continue;
                }

                $neighbor = $transition->to_status_id;

                if ($neighbor === $targetStatus->id) {
                    return array_merge($currentPath, [$neighbor]);
                }

                if (! isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = array_merge($currentPath, [$neighbor]);
                }
            }
        }

        throw StatusTransitionRejected::pathNotFound($startStatus->label, $targetStatus->label);
    }

    public function findDeterministicTransition(
        SubjectType $subjectType,
        int $subjectId,
        int $actorId,
        ?string $requiredPermission = null,
    ): Transition {
        $subject = $this->gateway($subjectType)->lock($subjectId);
        $fromStatus = Status::query()->findOrFail($subject->statusId);

        $query = Transition::query()
            ->where('from_status_id', $fromStatus->id)
            ->where('is_active', true)
            ->whereHas('toStatus', fn ($q) => $q->where('is_active', true)->where('type', $subject->type->value))
            ->with('toStatus');

        if ($requiredPermission !== null) {
            $query->where('required_permission', $requiredPermission);
        }

        /** @var Collection<int, Transition> $candidates */
        $candidates = $query->get();

        $eligible = $candidates->filter(
            fn (Transition $t): bool => $this->guard->isEligible($t, $actorId, $subject)
        )->values()->all();

        if (empty($eligible)) {
            throw StatusTransitionRejected::transitionNotFound($fromStatus->label);
        }

        usort($eligible, function (Transition $a, Transition $b): int {
            $sortOrderA = $a->toStatus->sort_order;
            $sortOrderB = $b->toStatus->sort_order;

            if ($sortOrderA !== $sortOrderB) {
                return $sortOrderA <=> $sortOrderB;
            }

            return $a->id <=> $b->id;
        });

        return $eligible[0];
    }

    private function gateway(SubjectType $type): WorkflowSubjectGateway
    {
        return match ($type) {
            SubjectType::Deal => $this->deals,
            SubjectType::Lead => $this->leads,
        };
    }
}
