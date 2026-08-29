<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEmailTemplates extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('marketing.email_templates.new')),
        ]);
    }
}
