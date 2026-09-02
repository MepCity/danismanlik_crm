<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teams\Pages;

use App\Domain\Access\Actions\SaveTeam;
use App\Filament\Resources\Teams\TeamResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveTeam::class)->execute(null, $data, $actor);
    }
}
