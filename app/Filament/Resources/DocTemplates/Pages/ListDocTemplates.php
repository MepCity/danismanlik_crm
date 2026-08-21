<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocTemplates\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\DocTemplates\DocTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListDocTemplates extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = DocTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('management.actions.create')),
        ]);
    }
}
