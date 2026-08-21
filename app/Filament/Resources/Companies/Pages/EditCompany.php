<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Domain\Crm\Models\Company;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Company, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveCompanyDirectoryEntry::class)->execute($record, $data, $actor);
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('panel.company_directory.updated');
    }
}
