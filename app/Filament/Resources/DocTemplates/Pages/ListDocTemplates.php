<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocTemplates\Pages;

use App\Filament\Resources\DocTemplates\DocTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListDocTemplates extends ListRecords
{
    protected static string $resource = DocTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('management.actions.create'))];
    }
}
