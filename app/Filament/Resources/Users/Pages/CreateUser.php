<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Access\Actions\SaveUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveUser::class)->execute(null, $data, $actor);
    }
}
