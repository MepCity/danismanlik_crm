<?php

declare(strict_types=1);

namespace App\Domain\Deal\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\NotificationWriter;
use App\Domain\Deal\Events\DealAssigned;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Models\User;
use App\Support\Authorization\PolicyDecision;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AssignDeal
{
    public function __construct(
        private PolicyDecision $authorization,
        private StatusMachineContract $statuses,
        private ActivityRecorder $activities,
        private NotificationWriter $notifications,
    ) {}

    public function handle(int $dealId, int $projectManagerId, int $targetStatusId, int $actorId): Deal
    {
        return DB::transaction(function () use ($dealId, $projectManagerId, $targetStatusId, $actorId): Deal {
            $deal = Deal::query()->lockForUpdate()->findOrFail($dealId);
            $actor = User::query()->find($actorId);
            $projectManager = User::query()->find($projectManagerId);

            if ($actor === null || ! $this->authorization->record($actor, 'deal.assign', $deal)) {
                throw ValidationException::withMessages(['projectManagerId' => trans('operations.assignment.forbidden')]);
            }

            if ($projectManager === null || ! $projectManager->is_active || ! $projectManager->hasRole('Proje Yöneticisi')) {
                throw ValidationException::withMessages(['projectManagerId' => trans('operations.assignment.invalid_manager')]);
            }

            $previous = $deal->projectManager;
            $deal->update(['pm_user_id' => $projectManager->id]);
            $this->statuses->transition(new StatusTransition(
                SubjectType::Deal,
                $deal->id,
                $targetStatusId,
                $actor->id,
            ));
            $this->activities->record(
                action: 'deal.assigned',
                payload: [
                    'from_assignee' => $previous === null ? null : ['id' => $previous->id, 'name' => $previous->name],
                    'to_assignee' => ['id' => $projectManager->id, 'name' => $projectManager->name],
                ],
                actorId: $actor->id,
                dealId: $deal->id,
                defaultSource: 'user',
            );
            $this->notifications->dealAssigned($projectManager->id, $deal->id, $deal->reference_no);
            event(new DealAssigned((string) $deal->id, (string) $projectManager->id, (string) $actor->id));

            return $deal->refresh();
        });
    }
}
