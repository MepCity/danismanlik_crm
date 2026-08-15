<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Pages;

use App\Domain\Program\Actions\SaveProgramConfiguration;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateProgram extends CreateRecord
{
    protected static string $resource = ProgramResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveProgramConfiguration::class)->execute(null, $data, $actor);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('management.program_setup.actions.create'));
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('management.program_setup.messages.created');
    }
}
