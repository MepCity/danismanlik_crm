<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\CustomerFlowAction;
use Filament\Resources\Pages\ListRecords;

final class ListCustomers extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            CustomerFlowAction::forSelection(),
        ]);
    }

    public function getSubheading(): string
    {
        return __('panel.customers.description');
    }

    protected function getAllRecordsViewLabel(): string
    {
        return __('panel.list.all_customers');
    }
}
