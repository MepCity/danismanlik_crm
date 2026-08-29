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

    /**
     * Runs once, while the form is first filled. A later Livewire hydration does
     * not call it again, so the "Yeni Aşama" entry point can only ever add a
     * single unsaved draft step, and nothing is written until the user saves.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ServiceWorkflow $workflow */
        $workflow = $this->record;

        $steps = $workflow->steps()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn ($step): array => [
                'id' => $step->id,
                'type' => $step->type,
                'title' => $step->title,
                'guidance' => $step->guidance,
                'attention_note' => $step->attention_note,
            ])->all();

        if ($this->wantsDraftStep()) {
            $steps[] = ['id' => null, 'type' => 'action', 'title' => '', 'guidance' => '', 'attention_note' => null];
        }

        return [...$data, 'steps' => $steps];
    }

    private function wantsDraftStep(): bool
    {
        return filter_var(request()->query('yeniAsama', false), FILTER_VALIDATE_BOOLEAN);
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
