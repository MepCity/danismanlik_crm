<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Events\LeadStatusChanged;
use App\Domain\Crm\Services\LeadWorkflowSubjectGateway;
use App\Domain\Deal\Events\DealStatusChanged;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Document\Services\RequiredDocumentDataReader;
use App\Support\Conditions\ConditionResult;
use App\Support\Events\DomainEvent;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubject;
use App\Support\Workflow\WorkflowSubjectGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class StatusMachine implements StatusMachineContract
{
    public function __construct(
        private readonly DealWorkflowSubjectGateway $deals,
        private readonly LeadWorkflowSubjectGateway $leads,
        private readonly RequiredDocumentDataReader $documents,
        private readonly TransitionGuard $guard,
        private readonly ActivityRecorder $activities,
    ) {}

    public function transition(StatusTransition $request): void
    {
        DB::transaction(function () use ($request): void {
            $changedAt = Carbon::now();
            $gateway = $this->gateway($request->subjectType);
            $subject = $gateway->lock($request->subjectId);
            $fromStatus = Status::query()->lockForUpdate()->findOrFail($subject->statusId);
            $toStatus = Status::query()->findOrFail($request->targetStatusId);

            if ($fromStatus->is_terminal) {
                throw StatusTransitionRejected::terminal($fromStatus->label);
            }

            if (! $toStatus->is_active || $toStatus->type !== $subject->type->value) {
                throw StatusTransitionRejected::targetUnavailable();
            }

            $transition = Transition::query()
                ->where('from_status_id', $fromStatus->id)
                ->where('to_status_id', $toStatus->id)
                ->first();

            if ($transition === null) {
                throw StatusTransitionRejected::undefined($fromStatus->label, $toStatus->label);
            }

            if (! $transition->is_active) {
                throw StatusTransitionRejected::inactive($fromStatus->label, $toStatus->label);
            }

            $this->guard($transition, $request->actorId, $subject);
            $this->evaluateCondition($transition, $subject);

            $revision = WorkflowRevision::query()
                ->where('effective_from', '<=', $changedAt)
                ->latest('effective_from')
                ->first();

            if ($revision === null) {
                throw StatusTransitionRejected::revisionMissing();
            }

            $history = StatusHistory::query()
                ->where($subject->type->value.'_id', $subject->id)
                ->whereNull('exited_at')
                ->lockForUpdate()
                ->first();

            if ($history === null) {
                throw StatusTransitionRejected::historyMissing();
            }

            $history->update(['exited_at' => $changedAt]);
            $gateway->updateStatus($subject->id, $toStatus->id, $changedAt);

            StatusHistory::query()->create([
                $subject->type->value.'_id' => $subject->id,
                'status_id' => $toStatus->id,
                'status_label_snapshot' => $toStatus->label,
                'workflow_revision_id' => $revision->id,
                'transition_id' => $transition->id,
                'entered_at' => $changedAt,
                'changed_by' => $request->actorId,
                'reason' => $request->reason,
            ]);

            $this->activities->recordStatusChanged(
                $subject->type,
                $subject->id,
                $request->actorId,
                ['id' => $fromStatus->id, 'label' => $fromStatus->label],
                ['id' => $toStatus->id, 'label' => $toStatus->label],
                $changedAt,
            );

            event($this->statusChangedEvent($subject, $fromStatus->id, $toStatus->id, $request->actorId));
        });
    }

    private function gateway(SubjectType $type): WorkflowSubjectGateway
    {
        return match ($type) {
            SubjectType::Deal => $this->deals,
            SubjectType::Lead => $this->leads,
        };
    }

    private function guard(Transition $transition, int $actorId, WorkflowSubject $subject): void
    {
        if (! $this->guard->isPermitted($transition, $actorId, $subject)) {
            throw StatusTransitionRejected::permission((string) $transition->required_permission);
        }
    }

    private function evaluateCondition(Transition $transition, WorkflowSubject $subject): void
    {
        $result = $this->guard->evaluateCondition($transition, $subject);

        if ($result->passed) {
            return;
        }

        $requiredDocuments = $subject->type === SubjectType::Deal
            ? $this->documents->readForDeal($subject->id)
            : [];
        $missingDocuments = $this->missingDocumentNames($result, $requiredDocuments);

        throw $missingDocuments !== []
            ? StatusTransitionRejected::missingDocuments($missingDocuments)
            : StatusTransitionRejected::condition(array_values(array_unique(array_map(
                static fn ($failure): string => $failure->field,
                $result->failures,
            ))));
    }

    /**
     * @param  list<array{name: string, status: string}>  $documents
     * @return list<string>
     */
    private function missingDocumentNames(ConditionResult $result, array $documents): array
    {
        $rejectedStatuses = [];

        foreach ($result->failures as $failure) {
            if ($failure->field === 'deal.required_documents.status' && $failure->operator === 'all_in') {
                $rejectedStatuses = [...$rejectedStatuses, ...$failure->rejectedValues];
            }
        }

        return array_values(array_map(
            static fn (array $document): string => $document['name'],
            array_filter(
                $documents,
                static fn (array $document): bool => in_array($document['status'], $rejectedStatuses, true),
            ),
        ));
    }

    private function statusChangedEvent(
        WorkflowSubject $subject,
        int $fromStatusId,
        int $toStatusId,
        int $actorId,
    ): DomainEvent {
        return match ($subject->type) {
            SubjectType::Deal => new DealStatusChanged(
                (string) $subject->id,
                (string) $fromStatusId,
                (string) $toStatusId,
                (string) $actorId,
            ),
            SubjectType::Lead => new LeadStatusChanged(
                (string) $subject->id,
                (string) $fromStatusId,
                (string) $toStatusId,
                (string) $actorId,
            ),
        };
    }
}
