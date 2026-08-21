<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceWorkflows\Pages;

use App\Domain\Program\Actions\SaveServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditServiceWorkflow extends EditRecord
{
    protected static string $resource = ServiceWorkflowResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ServiceWorkflow $workflow */
        $workflow = $this->record;

        return [
            ...$data,
            'steps' => $workflow->steps()->where('is_active', true)->get()->map(fn ($step): array => [
                'id' => $step->id,
                'type' => $step->type,
                'title' => $step->title,
                'guidance' => $step->guidance,
                'attention_note' => $step->attention_note,
            ])->all(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof ServiceWorkflow, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveServiceWorkflow::class)->execute($record, $data, $actor);
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('management.workflow_setup.messages.updated');
    }
}
