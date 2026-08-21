<?php

declare(strict_types=1);

namespace App\Domain\Program\Services;

use App\Domain\Program\Models\ServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflowStep;
use Illuminate\Validation\ValidationException;

final class ServiceWorkflowSnapshot
{
    /** @return array{name: string, description: string|null, steps: list<array{type: string, title: string, guidance: string, attention_note: string|null}>} */
    public function capture(int $workflowId): array
    {
        $workflow = ServiceWorkflow::query()
            ->with(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->whereKey($workflowId)
            ->where('is_active', true)
            ->first();

        if ($workflow === null || $workflow->steps->isEmpty()) {
            throw ValidationException::withMessages([
                'service_workflow_id' => trans('management.validation.workflow_active_required'),
            ]);
        }

        return [
            'name' => $workflow->name,
            'description' => $workflow->description,
            'steps' => $workflow->steps->map(static fn (ServiceWorkflowStep $step): array => [
                'type' => $step->type,
                'title' => $step->title,
                'guidance' => $step->guidance,
                'attention_note' => $step->attention_note,
            ])->all(),
        ];
    }
}
