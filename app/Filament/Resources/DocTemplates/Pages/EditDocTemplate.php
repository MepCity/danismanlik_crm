<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocTemplates\Pages;

use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\DocTemplates\DocTemplateResource;
use Filament\Resources\Pages\EditRecord;

final class EditDocTemplate extends EditRecord
{
    protected static string $resource = DocTemplateResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['condition_rules'] = ConditionEditor::stateFromCondition(is_array($data['condition'] ?? null) ? $data['condition'] : null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['condition'] = ConditionEditor::conditionFromState((array) ($data['condition_rules'] ?? []));
        unset($data['condition_rules']);

        return $data;
    }
}
