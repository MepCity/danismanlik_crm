<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teams\Pages;

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\Team;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Team, 404);
        $data['member_ids'] = $record->members()->pluck('users.id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Team, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveTeam::class)->execute($record, $data, $actor);
    }
}
