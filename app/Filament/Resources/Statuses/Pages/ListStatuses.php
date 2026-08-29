<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statuses\Pages;

use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\Statuses\StatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListStatuses extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('management.actions.create')),
        ]);
    }
}
