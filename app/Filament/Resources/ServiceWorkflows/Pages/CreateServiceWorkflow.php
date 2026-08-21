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
