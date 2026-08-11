<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\Models\Deal;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubject;
use App\Support\Workflow\WorkflowSubjectGateway;
use Illuminate\Support\Carbon;

final class DealWorkflowSubjectGateway implements WorkflowSubjectGateway
{
    /** @return list<int> */
    public function lockIdsByStatus(int $statusId): array
    {
        return Deal::query()->where('status_id', $statusId)->lockForUpdate()->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();
    }

    public function lock(int $subjectId): WorkflowSubject
    {
        $deal = Deal::query()->lockForUpdate()->findOrFail($subjectId);

        return new WorkflowSubject(
            SubjectType::Deal,
            $deal->id,
            $deal->company_id,
            $deal->status_id,
            $deal->requested_amount,
        );
    }

    public function updateStatus(int $subjectId, int $statusId, Carbon $changedAt): void
    {
        Deal::query()->whereKey($subjectId)->update([
            'status_id' => $statusId,
            'status_changed_at' => $changedAt,
        ]);
    }
}
