<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transitions\Pages;

use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\Transitions\TransitionResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateTransition extends CreateRecord
{
    protected static string $resource = TransitionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $reason = (string) $data['change_reason'];
        $data['condition'] = ConditionEditor::conditionFromState((array) ($data['condition_rules'] ?? []));
        unset($data['change_reason'], $data['condition_rules']);

        return app(WorkflowDeactivationService::class)->createTransition($data, $actor->id, $reason);
    }
}
