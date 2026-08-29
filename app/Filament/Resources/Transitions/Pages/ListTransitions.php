<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transitions\Pages;

use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\Transitions\TransitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTransitions extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = TransitionResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('management.actions.create')),
        ]);
    }
}
