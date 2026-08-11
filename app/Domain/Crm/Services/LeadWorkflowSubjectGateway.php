<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Lead;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubject;
use App\Support\Workflow\WorkflowSubjectGateway;
use Illuminate\Support\Carbon;

final class LeadWorkflowSubjectGateway implements WorkflowSubjectGateway
{
    public function lock(int $subjectId): WorkflowSubject
    {
        $lead = Lead::query()->lockForUpdate()->findOrFail($subjectId);

        return new WorkflowSubject(
            SubjectType::Lead,
            $lead->id,
            $lead->company_id,
            $lead->status_id,
        );
    }

    public function updateStatus(int $subjectId, int $statusId, Carbon $changedAt): void
    {
        Lead::query()->whereKey($subjectId)->update(['status_id' => $statusId]);
    }
}
