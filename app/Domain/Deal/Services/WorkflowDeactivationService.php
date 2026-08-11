<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Services\LeadWorkflowSubjectGateway;
use App\Domain\Deal\DTO\OrphanImpact;
use App\Domain\Deal\Exceptions\WorkflowDeactivationBlocked;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Support\Conditions\ConditionDefinition;
use App\Support\Conditions\ConditionEvaluator;
use App\Support\Workflow\SubjectType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class WorkflowDeactivationService
{
    public function __construct(
        private OrphanTransitionInspectorContract $orphans,
        private ActivityRecorder $activities,
        private ConditionEvaluator $conditions,
        private DealWorkflowSubjectGateway $deals,
        private LeadWorkflowSubjectGateway $leads,
    ) {}

    public function deactivateTransition(
        int $transitionId,
        int $actorId,
        string $reason,
        ?int $migrationTargetStatusId = null,
    ): void {
        DB::transaction(function () use ($transitionId, $actorId, $reason, $migrationTargetStatusId): void {
            $transition = Transition::query()->lockForUpdate()->findOrFail($transitionId);

            if (! $transition->is_active) {
                return;
            }

            $impact = $this->orphans->beforeTransitionDeactivation($transition->id);

            if ($impact->hasOrphans() && $migrationTargetStatusId === null) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $transition->update(['is_active' => false]);
            $revision = $this->recordRevision($actorId, $reason, $impact, $migrationTargetStatusId);
            $this->migrate($impact, $migrationTargetStatusId, $actorId, $reason, $revision);
        });
    }

    public function deactivateStatus(
        int $statusId,
        int $actorId,
        string $reason,
        ?int $migrationTargetStatusId = null,
    ): void {
        DB::transaction(function () use ($statusId, $actorId, $reason, $migrationTargetStatusId): void {
            $status = Status::query()->lockForUpdate()->findOrFail($statusId);

            if (! $status->is_active) {
                return;
            }

            $impact = $this->orphans->beforeStatusDeactivation($status->id);

            if ($impact->hasOrphans() && $migrationTargetStatusId === null) {
                throw new WorkflowDeactivationBlocked($impact);
            }

            $status->update(['is_active' => false]);
            $revision = $this->recordRevision($actorId, $reason, $impact, $migrationTargetStatusId);
            $this->migrate($impact, $migrationTargetStatusId, $actorId, $reason, $revision);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createStatus(array $attributes, int $actorId, string $reason): Status
    {
        return DB::transaction(function () use ($attributes, $actorId, $reason): Status {
            $status = Status::query()->create($attributes);
            $this->recordRevision($actorId, $reason);

            return $status;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateStatus(Status $status, array $attributes, int $actorId, string $reason): Status
    {
        return DB::transaction(function () use ($status, $attributes, $actorId, $reason): Status {
            $status->update($attributes);
            $this->recordRevision($actorId, $reason);

            return $status->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function createTransition(array $attributes, int $actorId, string $reason): Transition
    {
        return DB::transaction(function () use ($attributes, $actorId, $reason): Transition {
            $this->validateTransition($attributes);
            $transition = Transition::query()->create($attributes);
            $this->recordRevision($actorId, $reason);

            return $transition;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateTransition(Transition $transition, array $attributes, int $actorId, string $reason): Transition
    {
        return DB::transaction(function () use ($transition, $attributes, $actorId, $reason): Transition {
            $this->validateTransition($attributes);
            $transition->update($attributes);
            $this->recordRevision($actorId, $reason);

            return $transition->refresh();
        });
    }

    private function recordRevision(
        int $actorId,
        string $reason,
        ?OrphanImpact $impact = null,
        ?int $migrationTargetStatusId = null,
    ): WorkflowRevision {
        if (trim($reason) === '') {
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

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

        $snapshot = compact('statuses', 'transitions');

        if ($impact?->hasOrphans() === true) {
            $snapshot['bulk_transition'] = [
                'target_status_id' => $migrationTargetStatusId,
                'subject_count' => $impact->subjectCount(),
                'source_status_ids' => array_map(
                    static fn ($status): int => $status->statusId,
                    $impact->statuses,
                ),
            ];
        }

        return WorkflowRevision::query()->create([
            'snapshot' => $snapshot,
            'effective_from' => now(),
            'changed_by' => $actorId,
            'reason' => $reason,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function validateTransition(array $attributes): void
    {
        $from = Status::query()->findOrFail((int) ($attributes['from_status_id'] ?? 0));
        $to = Status::query()->findOrFail((int) ($attributes['to_status_id'] ?? 0));

        if ($from->id === $to->id || $from->type !== $to->type) {
            throw new InvalidArgumentException(__('management.validation.migration_target_invalid'));
        }

        $condition = $attributes['condition'] ?? null;
        if (is_array($condition)) {
            ConditionDefinition::validate($condition, $this->conditions);
        }
    }

    private function migrate(
        OrphanImpact $impact,
        ?int $targetStatusId,
        int $actorId,
        string $reason,
        WorkflowRevision $revision,
    ): void {
        if (! $impact->hasOrphans()) {
            return;
        }

        if ($targetStatusId === null) {
            throw new WorkflowDeactivationBlocked($impact);
        }

        $target = Status::query()->lockForUpdate()->findOrFail($targetStatusId);

        if (! $target->is_active) {
            throw new InvalidArgumentException(__('management.validation.migration_target_inactive'));
        }

        $changedAt = Carbon::now();
        $firstSubject = null;
        $sourceLabels = [];

        foreach ($impact->statuses as $orphan) {
            if ($target->type !== $orphan->subjectType->value || $target->id === $orphan->statusId) {
                throw new InvalidArgumentException(__('management.validation.migration_target_invalid'));
            }

            $subjectIds = $this->subjectIds($orphan->subjectType, $orphan->statusId);
            $sourceLabels[] = $orphan->statusLabel;

            foreach ($subjectIds as $subjectId) {
                $firstSubject ??= [$orphan->subjectType, $subjectId];
                StatusHistory::query()
                    ->where($orphan->subjectType->value.'_id', $subjectId)
                    ->whereNull('exited_at')
                    ->update(['exited_at' => $changedAt]);

                $this->updateSubject($orphan->subjectType, $subjectId, $target->id, $changedAt);

                StatusHistory::query()->create([
                    $orphan->subjectType->value.'_id' => $subjectId,
                    'status_id' => $target->id,
                    'status_label_snapshot' => $target->label,
                    'workflow_revision_id' => $revision->id,
                    'entered_at' => $changedAt,
                    'changed_by' => $actorId,
                    'reason' => $reason,
                ]);
            }
        }

        if ($firstSubject !== null) {
            [$type, $id] = $firstSubject;
            $this->activities->record(
                action: 'workflow.bulk_transition',
                payload: [
                    'source_statuses' => $sourceLabels,
                    'target_status' => ['id' => $target->id, 'label' => $target->label],
                    'subject_count' => $impact->subjectCount(),
                    'reason' => $reason,
                ],
                actorId: $actorId,
                leadId: $type === SubjectType::Lead ? $id : null,
                dealId: $type === SubjectType::Deal ? $id : null,
                occurredAt: $changedAt,
                defaultSource: 'user',
            );
        }
    }

    /** @return list<int> */
    private function subjectIds(SubjectType $type, int $statusId): array
    {
        return match ($type) {
            SubjectType::Deal => $this->deals->lockIdsByStatus($statusId),
            SubjectType::Lead => $this->leads->lockIdsByStatus($statusId),
        };
    }

    private function updateSubject(SubjectType $type, int $subjectId, int $statusId, Carbon $changedAt): void
    {
        match ($type) {
            SubjectType::Deal => $this->deals->updateStatus($subjectId, $statusId, $changedAt),
            SubjectType::Lead => $this->leads->updateStatus($subjectId, $statusId, $changedAt),
        };
    }
}
