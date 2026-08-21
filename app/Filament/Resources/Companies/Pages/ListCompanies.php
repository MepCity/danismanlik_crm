<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListCompanies extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            Action::make('sort_list')
                ->label(__('panel.list.sort'))
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'column' => $this->getTableSortColumn() ?: 'legal_name',
                    'direction' => $this->getTableSortDirection() ?: 'asc',
                ])
                ->form([
                    Select::make('column')
                        ->label(__('panel.list.sort_by'))
                        ->options([
                            'legal_name' => __('panel.fields.legal_name'),
                            'industry' => __('panel.fields.industry'),
                            'city' => __('panel.fields.city'),
                        ])
                        ->required(),
                    Select::make('direction')
                        ->label(__('panel.list.sort_direction'))
                        ->options([
                            'asc' => __('panel.list.ascending'),
                            'desc' => __('panel.list.descending'),
                        ])
                        ->required(),
                ])
                ->modalSubmitActionLabel(__('panel.list.apply_sort'))
                ->action(fn (array $data): null => $this->applyListSort($data)),
            CreateAction::make()->label(__('panel.company_directory.create')),
        ]);
    }

    /** @param array{column: string, direction: string} $data */
    private function applyListSort(array $data): null
    {
        $this->sortTable($data['column'], $data['direction']);

        return null;
    }

    public function getSubheading(): string
    {
        return __('panel.company_directory.description');
    }

    protected function getAllRecordsViewLabel(): string
    {
        return __('panel.list.all_companies');
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('panel.list.all_companies')),
            'active' => Tab::make(__('panel.list.active_companies'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)),
            'directory_only' => Tab::make(__('panel.list.directory_only'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDoesntHave('deals')),
        ];
    }

    /** @return array<string, mixed> */
    public function filterSnapshot(): array
    {
        return array_filter([
            'view' => $this->activeTab,
            'industry' => $this->getTableFilterState('industry'),
            'city' => $this->getTableFilterState('city'),
            'size' => $this->getTableFilterState('size'),
            'is_active' => $this->getTableFilterState('is_active'),
            'customer_state' => $this->getTableFilterState('customer_state'),
        ], static fn (mixed $value): bool => filled($value));
    }
}
