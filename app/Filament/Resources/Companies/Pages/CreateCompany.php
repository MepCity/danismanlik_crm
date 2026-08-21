<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveCompanyDirectoryEntry::class)->execute(null, $data, $actor);
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('panel.company_directory.created');
    }
}
