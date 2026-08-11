<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\DTO\ChecklistDeal;
use App\Domain\Deal\Models\Deal;
use Illuminate\Support\Carbon;

final class ChecklistDealGateway
{
    public function lock(int $dealId): ChecklistDeal
    {
        return $this->toData(Deal::query()->lockForUpdate()->findOrFail($dealId));
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
            $deal->company_id,
            $deal->program_version_id,
            $deal->pm_user_id,
            $deal->opened_by_user_id,
            $deal->requested_amount,
        );
    }
}
