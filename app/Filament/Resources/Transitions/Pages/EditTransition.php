<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transitions\Pages;

use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\Transitions\TransitionResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditTransition extends EditRecord
{
    protected static string $resource = TransitionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['condition_rules'] = ConditionEditor::stateFromCondition(is_array($data['condition'] ?? null) ? $data['condition'] : null);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Transition, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $reason = (string) $data['change_reason'];
        $data['condition'] = ConditionEditor::conditionFromState((array) ($data['condition_rules'] ?? []));
        unset($data['change_reason'], $data['condition_rules']);

        return app(WorkflowDeactivationService::class)->updateTransition($record, $data, $actor->id, $reason);
    }
}
