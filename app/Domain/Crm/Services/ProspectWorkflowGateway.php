<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\DTOs\InitialLeadState;
use Illuminate\Support\Facades\DB;

final class ProspectWorkflowGateway
{
    public function initialState(): InitialLeadState
    {
        $status = DB::table('statuses')->where('type', 'lead')->where('is_initial', true)->where('is_active', true)->sole(['id', 'label']);
        $revisionId = DB::table('workflow_revisions')->where('effective_from', '<=', now())->latest('effective_from')->value('id');

        abort_if($revisionId === null, 422);

        return new InitialLeadState((int) $status->id, (string) $status->label, (int) $revisionId);
    }

    public function recordInitialHistory(int $leadId, InitialLeadState $state, int $actorId): void
    {
        DB::table('status_history')->insert([
            'lead_id' => $leadId,
            'deal_id' => null,
            'status_id' => $state->statusId,
            'status_label_snapshot' => $state->statusLabel,
            'workflow_revision_id' => $state->workflowRevisionId,
            'transition_id' => null,
            'entered_at' => now(),
            'exited_at' => null,
            'changed_by' => $actorId,
            'reason' => trans('marketing.intake.history_reason'),
        ]);
    }

    public function activeProgramVersionExists(int $programVersionId): bool
    {
        return DB::table('program_versions')->where('id', $programVersionId)->where('is_active', true)->exists();
    }

    /** @return list<int> */
    public function transitionPath(int $initialStatusId, int $targetStatusId): array
    {
        if (DB::table('transitions')->where('from_status_id', $initialStatusId)->where('to_status_id', $targetStatusId)->where('is_active', true)->exists()) {
            return [$targetStatusId];
        }

        $intermediate = DB::table('transitions as first')
            ->join('transitions as second', 'second.from_status_id', '=', 'first.to_status_id')
            ->join('statuses as target', 'target.id', '=', 'second.to_status_id')
            ->where('first.from_status_id', $initialStatusId)
            ->where('second.to_status_id', $targetStatusId)
            ->where('first.is_active', true)
            ->where('second.is_active', true)
            ->where('target.is_active', true)
            ->where('target.type', 'lead')
            ->where('target.converts_to_deal', false)
            ->value('first.to_status_id');

        return $intermediate === null ? [] : [(int) $intermediate, $targetStatusId];
    }
}
