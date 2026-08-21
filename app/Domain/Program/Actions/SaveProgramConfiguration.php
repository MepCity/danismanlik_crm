<?php

declare(strict_types=1);

namespace App\Domain\Program\Actions;

use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Program\Services\ServiceWorkflowSnapshot;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveProgramConfiguration
{
    public function __construct(private readonly ServiceWorkflowSnapshot $workflows) {}

    /** @param array<string, mixed> $data */
    public function execute(?Program $program, array $data, User $actor): Program
    {
        $version = $program?->versions()->latest('id')->first();
        $validated = $this->validate($data, $program, $version);

        return DB::transaction(function () use ($program, $version, $validated, $actor): Program {
            if ($program === null) {
                Gate::forUser($actor)->authorize('create', Program::class);
                $program = Program::query()->create([
                    'name' => $validated['name'],
                    'institution' => $validated['institution'],
                    'code' => $this->nextCode($validated['name']),
                    'is_active' => false,
                ]);
            } else {
                Gate::forUser($actor)->authorize('update', $program);
                $program->update([
                    'name' => $validated['name'],
                    'institution' => $validated['institution'],
                ]);
            }

            if ($version === null) {
                Gate::forUser($actor)->authorize('create', ProgramVersion::class);
                $version = ProgramVersion::query()->create([
                    'program_id' => $program->id,
                    ...$this->versionAttributes($validated, $program->is_active),
                ]);
            } else {
                Gate::forUser($actor)->authorize('update', $version);
                $version->update($this->versionAttributes($validated, $program->is_active));
            }

            $this->syncDocuments($version, $validated['documents'], $actor);

            return $program->refresh()->load(['versions.docTemplates']);
        });
    }

    /** @param array<string, mixed> $data
     * @return array{name: string, institution: string, is_active: bool, service_workflow_id: int|null, call_period: string, application_opens_at: mixed, application_closes_at: mixed, description: mixed, documents: list<array{id?: int|null, name: string, description?: mixed, is_required: bool, accepted_formats: list<string>, validity_days?: int|null}>}
     */
    private function validate(array $data, ?Program $program, ?ProgramVersion $version): array
    {
        $programId = $program === null ? 0 : $program->id;

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'institution' => ['required', Rule::in(array_keys((array) trans('management.institutions')))],
            'is_active' => ['required', 'boolean'],
            'service_workflow_id' => ['nullable', 'integer', Rule::exists('service_workflows', 'id')->where('is_active', true)],
            'call_period' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('program_versions', 'call_period')
                    ->where(fn (Builder $query): Builder => $query->where('program_id', $programId))
                    ->ignore($version === null ? null : $version->id),
            ],
            'application_opens_at' => ['nullable', 'date'],
            'application_closes_at' => ['nullable', 'date', 'after:application_opens_at'],
            'description' => ['nullable', 'string', 'max:10000'],
            'documents' => ['array'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.name' => ['required', 'string', 'max:255', 'distinct:strict'],
            'documents.*.description' => ['nullable', 'string', 'max:5000'],
            'documents.*.is_required' => ['required', 'boolean'],
            'documents.*.accepted_formats' => ['required', 'array', 'min:1'],
            'documents.*.accepted_formats.*' => [Rule::in(array_keys((array) trans('management.formats')))],
            'documents.*.validity_days' => ['nullable', 'integer', 'min:1'],
        ], [
            'documents.required' => trans('management.validation.documents_required'),
            'documents.min' => trans('management.validation.documents_required'),
            'documents.*.name.distinct' => trans('management.validation.document_names_unique'),
        ]);

        /** @var array{name: string, institution: string, is_active: bool, service_workflow_id: int|null, call_period: string, application_opens_at: mixed, application_closes_at: mixed, description: mixed, documents: list<array{id?: int|null, name: string, description?: mixed, is_required: bool, accepted_formats: list<string>, validity_days?: int|null}>} $validated */
        $validated = $validator->validate();

        return $validated;
    }

    /** @param array<string, mixed> $validated
     * @return array{service_workflow_id: int|null, call_period: mixed, application_opens_at: mixed, application_closes_at: mixed, description: mixed, workflow_snapshot: array<string, mixed>|null, is_active: bool}
     */
    private function versionAttributes(array $validated, bool $isActive): array
    {
        $workflowId = isset($validated['service_workflow_id']) ? (int) $validated['service_workflow_id'] : null;

        return [
            'service_workflow_id' => $workflowId,
            'call_period' => $validated['call_period'],
            'application_opens_at' => $validated['application_opens_at'] ?? null,
            'application_closes_at' => $validated['application_closes_at'] ?? null,
            'description' => $validated['description'] ?? null,
            'workflow_snapshot' => $workflowId === null ? null : $this->workflows->capture($workflowId),
            'is_active' => $isActive,
        ];
    }

    /** @param list<array{id?: int|null, name: string, description?: mixed, is_required: bool, accepted_formats: list<string>, validity_days?: int|null}> $documents */
    private function syncDocuments(ProgramVersion $version, array $documents, User $actor): void
    {
        $keptIds = [];

        foreach ($documents as $order => $data) {
            $template = isset($data['id'])
                ? $version->docTemplates()->whereKey((int) $data['id'])->first()
                : $version->docTemplates()->where('name', $data['name'])->first();

            if (isset($data['id']) && ! $template instanceof DocTemplate) {
                throw ValidationException::withMessages([
                    'documents' => trans('management.validation.document_not_in_program'),
                ]);
            }

            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_required' => $data['is_required'],
                'accepted_formats' => $data['accepted_formats'],
                'validity_days' => $data['validity_days'] ?? null,
                'sort_order' => $order,
                'is_active' => true,
            ];

            if ($template instanceof DocTemplate) {
                Gate::forUser($actor)->authorize('update', $template);
                $template->update($attributes);
            } else {
                Gate::forUser($actor)->authorize('create', DocTemplate::class);
                $template = DocTemplate::query()->create([
                    'program_version_id' => $version->id,
                    'condition' => null,
                    ...$attributes,
                ]);
            }

            $keptIds[] = $template->id;
        }

        $version->docTemplates()->where('is_active', true)->whereNotIn('id', $keptIds)->get()
            ->each(function (DocTemplate $template) use ($actor): void {
                Gate::forUser($actor)->authorize('update', $template);
                $template->update(['is_active' => false]);
            });
    }

    private function nextCode(string $name): string
    {
        $base = Str::upper(Str::slug($name));
        $base = $base !== '' ? $base : 'PROGRAM';
        $code = $base;
        $suffix = 2;

        while (Program::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
