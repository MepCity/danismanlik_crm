<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceWorkflows\Pages;

use App\Domain\Program\Actions\SaveServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateServiceWorkflow extends CreateRecord
{
    protected static string $resource = ServiceWorkflowResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * The list screen hands over the name typed into its inline control as a
     * request parameter. Nothing is persisted until the domain action runs with
     * at least one step, and nothing is stored in the session.
     */
    protected function fillForm(): void
    {
        $name = request()->query('name');

        $this->form->fill([
            'name' => is_string($name) ? mb_substr(trim($name), 0, 255) : null,
            'is_active' => true,
            'steps' => [['type' => 'action', 'title' => '', 'guidance' => '', 'attention_note' => null]],
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveServiceWorkflow::class)->execute(null, $data, $actor);
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('management.workflow_setup.messages.created');
    }
}
