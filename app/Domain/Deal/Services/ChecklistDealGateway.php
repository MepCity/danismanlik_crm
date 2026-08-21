<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\DTO\ChecklistDeal;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use Illuminate\Support\Carbon;

final class ChecklistDealGateway
{
    /** @param array<string, mixed>|null $workflowSnapshot */
    public function createAwaitingAssignment(int $companyId, int $programVersionId, ?array $workflowSnapshot, int $actorId, string $reference, string $reason): ChecklistDeal
    {
        $initial = Status::query()->where('type', 'deal')->where('is_initial', true)->where('is_active', true)->sole();
        $revision = WorkflowRevision::query()->where('effective_from', '<=', now())->latest('effective_from')->firstOrFail();
        $deal = Deal::query()->create([
            'company_id' => $companyId,
            'program_version_id' => $programVersionId,
            'workflow_snapshot' => $workflowSnapshot,
            'reference_no' => $reference,
            'status_id' => $initial->id,
            'status_changed_at' => now(),
            'opened_by_user_id' => $actorId,
            'requested_amount' => '0.00',
            'priority' => 'normal',
        ]);
        StatusHistory::query()->create([
            'deal_id' => $deal->id,
            'status_id' => $initial->id,
            'status_label_snapshot' => $initial->label,
            'workflow_revision_id' => $revision->id,
            'entered_at' => $deal->status_changed_at,
            'changed_by' => $actorId,
            'reason' => $reason,
        ]);

        return $this->toData($deal);
    }

    public function lock(int $dealId): ChecklistDeal
    {
        return $this->toData(Deal::query()->lockForUpdate()->findOrFail($dealId));
    }

    public function find(int $dealId): ChecklistDeal
    {
        return $this->toData(Deal::query()->findOrFail($dealId));
    }

    /** @return list<int> */
    public function idsForCompany(int $companyId): array
    {
        return Deal::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function setDocumentRequestedAtIfMissing(int $dealId, Carbon $requestedAt): void
    {
        Deal::query()->whereKey($dealId)->whereNull('document_requested_at')->update([
            'document_requested_at' => $requestedAt,
        ]);
    }

    public function setFirstDocumentReceivedAtIfMissing(int $dealId, Carbon $receivedAt): bool
    {
        return Deal::query()->whereKey($dealId)->whereNull('first_document_received_at')->update([
            'first_document_received_at' => $receivedAt,
        ]) === 1;
    }

    public function setDocumentCompletion(int $dealId, bool $complete, Carbon $at): void
    {
        $deal = Deal::query()->lockForUpdate()->findOrFail($dealId);
        $deal->update([
            'all_required_accepted_at' => $complete
                ? ($deal->all_required_accepted_at ?? $at)
                : null,
        ]);
    }

    private function toData(Deal $deal): ChecklistDeal
    {
        return new ChecklistDeal(
            $deal->id,
            $deal->reference_no,
            $deal->company_id,
            $deal->program_version_id,
            $deal->pm_user_id,
            $deal->opened_by_user_id,
            $deal->requested_amount,
        );
    }
}
