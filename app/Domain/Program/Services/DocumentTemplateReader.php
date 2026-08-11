<?php

declare(strict_types=1);

namespace App\Domain\Program\Services;

use App\Domain\Program\DTOs\DocumentTemplateData;
use App\Domain\Program\Models\DocTemplate;

final class DocumentTemplateReader
{
    /** @return list<DocumentTemplateData> */
    public function activeForVersion(int $programVersionId, bool $conditionalOnly = false): array
    {
        $query = DocTemplate::query()
            ->where('program_version_id', $programVersionId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($conditionalOnly) {
            $query->whereNotNull('condition');
        }

        return $query->get()->map(static fn (DocTemplate $template): DocumentTemplateData => new DocumentTemplateData(
            $template->id,
            $template->name,
            $template->description,
            $template->is_required,
            $template->condition,
        ))->all();
    }
}
