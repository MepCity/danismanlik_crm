<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCompanies extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CreateAction::make()->label(__('panel.company_directory.create')),
        ]);
    }

    public function getSubheading(): string
    {
        return __('panel.company_directory.description');
    }

    protected function getAllRecordsViewLabel(): string
    {
        return __('panel.list.all_companies');
    }
}
