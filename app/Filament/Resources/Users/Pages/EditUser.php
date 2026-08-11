<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Access\Actions\SaveUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        abort_unless($record instanceof User, 404);
        $data['role_ids'] = $record->roles()->pluck('roles.id')->all();
        $data['team_ids'] = $record->teams()->pluck('teams.id')->all();
        $data['password'] = null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof User, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveUser::class)->execute($record, $data, $actor);
    }
}
