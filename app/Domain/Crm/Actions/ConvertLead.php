<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\NotificationWriter;
use App\Domain\Crm\Events\LeadConverted;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Services\LeadWorkflowSubjectGateway;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Models\User;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConvertLead
{
    public function __construct(
        private StatusMachineContract $statuses,
        private ChecklistGeneratorContract $checklists,
        private ActivityRecorder $activities,
        private NotificationWriter $notifications,
        private LeadWorkflowSubjectGateway $leads,
        private ChecklistDealGateway $deals,
    ) {}

    public function handle(int $leadId, int $wonStatusId, int $programVersionId, int $actorId): int
    {
        return DB::transaction(function () use ($leadId, $wonStatusId, $programVersionId, $actorId): int {
            $lead = Lead::query()->lockForUpdate()->findOrFail($leadId);

            if ($lead->converted_deal_id !== null) {
                throw ValidationException::withMessages(['transitionTargetId' => trans('marketing.validation.already_converted')]);
            }

            $won = $this->leads->target($wonStatusId);
            abort_unless($won->convertsToDeal, 422);

            $this->statuses->transition(new StatusTransition(SubjectType::Lead, $lead->id, $won->id, $actorId));

            $deal = $this->deals->createAwaitingAssignment(
                $lead->company_id,
                $programVersionId,
                $actorId,
                'BLF-L'.$lead->id,
                trans('marketing.conversion.history_reason'),
            );
            $this->checklists->generate($deal->id, $actorId);
            $lead->update(['converted_deal_id' => $deal->id, 'interested_program_version_id' => $programVersionId]);

            $this->activities->record(
                action: 'lead.converted',
                payload: ['deal_id' => $deal->id, 'deal_reference' => 'BLF-L'.$lead->id],
                actorId: $actorId,
                leadId: $lead->id,
                defaultSource: 'user',
            );

            User::permission('deal.assign')->where('is_active', true)->each(
                fn (User $user) => $this->notifications->assignmentPending($user->id, $deal->id, 'BLF-L'.$lead->id),
            );
            event(new LeadConverted((string) $lead->id, (string) $deal->id, (string) $actorId));

            return $deal->id;
        });
    }
}
