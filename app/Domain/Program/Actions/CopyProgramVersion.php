<?php

declare(strict_types=1);

namespace App\Domain\Program\Actions;

use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\ProgramVersion;
use Illuminate\Support\Facades\DB;

final class CopyProgramVersion
{
    /** @param array{call_period: string, application_opens_at?: mixed, application_closes_at?: mixed, description?: mixed, is_active?: bool} $attributes */
    public function execute(ProgramVersion $source, array $attributes): ProgramVersion
    {
        return DB::transaction(function () use ($source, $attributes): ProgramVersion {
            $target = ProgramVersion::query()->create([
                'program_id' => $source->program_id,
                'service_workflow_id' => $source->service_workflow_id,
                'workflow_snapshot' => $source->workflow_snapshot,
                ...$attributes,
            ]);

            foreach ($source->docTemplates()->orderBy('sort_order')->get() as $template) {
                DocTemplate::query()->create([
                    'program_version_id' => $target->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'is_required' => $template->is_required,
                    'condition' => $template->condition,
                    'accepted_formats' => $template->accepted_formats,
                    'validity_days' => $template->validity_days,
                    'sort_order' => $template->sort_order,
                    'is_active' => $template->is_active,
                ]);
            }

            return $target->load('docTemplates');
        });
    }
}
