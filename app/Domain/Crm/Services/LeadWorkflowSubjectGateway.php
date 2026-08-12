<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\DTOs\LeadStatusTarget;
use App\Domain\Crm\Models\Lead;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubject;
use App\Support\Workflow\WorkflowSubjectGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LeadWorkflowSubjectGateway implements WorkflowSubjectGateway
{
    public function target(int $statusId): LeadStatusTarget
    {
        $row = DB::table('statuses')->where('type', 'lead')->where('id', $statusId)->first([
            'id', 'required_fields', 'converts_to_deal',
        ]);

        if ($row === null) {
            throw new ModelNotFoundException;
        }

        $required = json_decode((string) $row->required_fields, true, flags: JSON_THROW_ON_ERROR);

        return new LeadStatusTarget(
            (int) $row->id,
            is_array($required) ? array_values(array_filter($required, 'is_string')) : [],
            (bool) $row->converts_to_deal,
        );
    }

    /** @return list<int> */
    public function lockIdsByStatus(int $statusId): array
    {
        return Lead::query()->where('status_id', $statusId)->lockForUpdate()->pluck('id')->map(
            static fn (mixed $id): int => (int) $id,
        )->all();
    }

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
