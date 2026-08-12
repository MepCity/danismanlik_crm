<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\DTOs\LeadStatusTarget;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Services\LeadWorkflowSubjectGateway;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionLead
{
    public function __construct(
        private StatusMachineContract $statuses,
        private ConvertLead $converter,
        private LeadWorkflowSubjectGateway $leads,
    ) {}

    public function handle(
        int $leadId,
        int $targetStatusId,
        int $actorId,
        ?string $nextCallAt = null,
        ?int $ownerUserId = null,
        ?string $lostReason = null,
        ?int $programVersionId = null,
    ): ?int {
        $target = $this->leads->target($targetStatusId);
        $this->validateRequired($target, $nextCallAt, $ownerUserId, $lostReason, $programVersionId);

        if ($target->convertsToDeal) {
            return $this->converter->handle($leadId, $targetStatusId, (int) $programVersionId, $actorId);
        }

        DB::transaction(function () use ($leadId, $targetStatusId, $actorId, $nextCallAt, $ownerUserId, $lostReason): void {
            Lead::query()->whereKey($leadId)->update([
                'next_call_at' => filled($nextCallAt) ? $nextCallAt : null,
                'owner_user_id' => $ownerUserId ?? Lead::query()->findOrFail($leadId)->owner_user_id,
                'lost_reason' => filled($lostReason) ? trim((string) $lostReason) : null,
            ]);
            $this->statuses->transition(new StatusTransition(SubjectType::Lead, $leadId, $targetStatusId, $actorId, $lostReason));
        });

        return null;
    }

    private function validateRequired(LeadStatusTarget $target, ?string $nextCallAt, ?int $ownerUserId, ?string $lostReason, ?int $programVersionId): void
    {
        $values = compact('nextCallAt', 'ownerUserId', 'lostReason', 'programVersionId');
        $keys = [
            'next_call_at' => 'nextCallAt',
            'owner_user_id' => 'ownerUserId',
            'lost_reason' => 'lostReason',
            'program_version_id' => 'programVersionId',
        ];
        $errors = [];

        foreach ($target->requiredFields as $field) {
            $property = $keys[$field] ?? null;
            if ($property !== null && blank($values[$property])) {
                $errors[$property] = trans('marketing.validation.'.$field.'_required');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
