<?php

declare(strict_types=1);

namespace App\Filament\Resources\BreakGlassGrants\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\BreakGlassGrants\BreakGlassGrantResource;
use Filament\Resources\Pages\ListRecords;

final class ListBreakGlassGrants extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = BreakGlassGrantResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions();
    }
}
