<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\Programs\ProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPrograms extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('management.program_setup.actions.new')),
        ]);
    }
}
