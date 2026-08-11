<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Access\Actions\UpdateRolePermissions;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        abort_unless($record instanceof Role, 404);
        $data['permission_ids'] = $record->permissions()->pluck('permissions.id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Role, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(UpdateRolePermissions::class)->execute(
            $record,
            array_map('intval', (array) $data['permission_ids']),
            (string) $data['change_reason'],
            $actor,
            ['default_scope' => $data['default_scope'], 'is_active' => $data['is_active']],
        );
    }
}
