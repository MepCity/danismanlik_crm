<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocTemplates\Pages;

use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\DocTemplates\DocTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateDocTemplate extends CreateRecord
{
    protected static string $resource = DocTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['condition'] = ConditionEditor::conditionFromState((array) ($data['condition_rules'] ?? []));
        unset($data['condition_rules']);

        return $data;
    }
}
