<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\Teams\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTeams extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('management.actions.create')),
        ]);
    }
}
