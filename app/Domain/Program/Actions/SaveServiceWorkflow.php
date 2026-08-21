<?php

declare(strict_types=1);

namespace App\Domain\Program\Actions;

use App\Domain\Program\Models\ServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflowStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveServiceWorkflow
{
    /** @param array<string, mixed> $data */
    public function execute(?ServiceWorkflow $workflow, array $data, User $actor): ServiceWorkflow
    {
        Gate::forUser($actor)->authorize($workflow === null ? 'create' : 'update', $workflow ?? ServiceWorkflow::class);

        /** @var array{name: string, description?: string|null, is_active: bool, steps: list<array{id?: int|null, type: string, title: string, guidance: string, attention_note?: string|null}>} $validated */
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['nullable', 'integer'],
            'steps.*.type' => ['required', Rule::in(['action', 'waiting', 'decision'])],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.guidance' => ['required', 'string', 'max:5000'],
            'steps.*.attention_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'steps.required' => trans('management.validation.workflow_steps_required'),
            'steps.min' => trans('management.validation.workflow_steps_required'),
        ])->validate();

        return DB::transaction(function () use ($workflow, $validated): ServiceWorkflow {
            $workflow ??= new ServiceWorkflow;
            $workflow->fill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
            ])->save();

            $keptIds = [];

            foreach ($validated['steps'] as $order => $stepData) {
                $step = isset($stepData['id'])
                    ? $workflow->steps()->whereKey((int) $stepData['id'])->first()
                    : null;

                if (isset($stepData['id']) && ! $step instanceof ServiceWorkflowStep) {
                    throw ValidationException::withMessages([
                        'steps' => trans('management.validation.workflow_step_not_owned'),
                    ]);
                }

                $step ??= new ServiceWorkflowStep(['service_workflow_id' => $workflow->id]);
                $step->fill([
                    'type' => $stepData['type'],
                    'title' => $stepData['title'],
                    'guidance' => $stepData['guidance'],
                    'attention_note' => $stepData['attention_note'] ?? null,
                    'sort_order' => $order,
                    'is_active' => true,
                ])->save();
                $keptIds[] = $step->id;
            }

            $workflow->steps()->where('is_active', true)->whereNotIn('id', $keptIds)->update(['is_active' => false]);

            return $workflow->refresh()->load('steps');
        });
    }
}
