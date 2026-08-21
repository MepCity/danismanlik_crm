<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ListRecords;

final class ListRoles extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions();
    }
}
