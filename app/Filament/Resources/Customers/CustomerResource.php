<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\ScopedResource;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** @extends ScopedResource<Company> */
final class CustomerResource extends ScopedResource
{
    protected static ?string $model = Company::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.crm');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.customers.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.customers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.customers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $actor = auth()->user();
                if (! $actor instanceof User) {
                    return $query->whereRaw('1 = 0');
                }

                $visibleDealIds = app(ScopedQuery::class)
                    ->apply(Deal::query(), $actor)
                    ->select('deals.id');

                return $query
                    ->whereHas('deals', fn (Builder $deals): Builder => $deals->whereIn('deals.id', clone $visibleDealIds))
                    ->withCount([
                        'deals as active_deals_count' => fn (Builder $deals): Builder => $deals
                            ->whereIn('deals.id', clone $visibleDealIds)
                            ->whereHas('status', fn (Builder $status): Builder => $status->where('is_terminal', false)),
                    ])
                    ->withMin([
                        'deals as deals_min_created_at' => fn (Builder $deals): Builder => $deals
                            ->whereIn('deals.id', clone $visibleDealIds),
                    ], 'created_at')
                    ->addSelect([
                        'latest_deal_status' => Deal::query()
                            ->select('statuses.label')
                            ->join('statuses', 'statuses.id', '=', 'deals.status_id')
                            ->whereColumn('deals.company_id', 'companies.id')
                            ->whereIn('deals.id', clone $visibleDealIds)
                            ->orderByDesc('deals.created_at')
                            ->orderByDesc('deals.id')
                            ->limit(1),
                        'latest_deal_pm' => Deal::query()
                            ->select('users.name')
                            ->join('users', 'users.id', '=', 'deals.pm_user_id')
                            ->whereColumn('deals.company_id', 'companies.id')
                            ->whereIn('deals.id', clone $visibleDealIds)
                            ->orderByDesc('deals.created_at')
                            ->orderByDesc('deals.id')
                            ->limit(1),
                    ]);
            })
            ->columns([
                TextColumn::make('legal_name')->label(__('panel.fields.legal_name'))->searchable()->sortable(),
                TextColumn::make('industry')->label(__('panel.fields.industry'))->formatStateUsing(fn (string $state): string => __('panel.industries.'.$state))->sortable(),
                TextColumn::make('city')->label(__('panel.fields.city'))->sortable(),
                TextColumn::make('active_deals_count')->label(__('panel.customers.active_projects'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                TextColumn::make('latest_deal_status')->label(__('panel.customers.latest_status'))->placeholder('—'),
                TextColumn::make('latest_deal_pm')->label(__('panel.customers.project_manager'))->placeholder(__('operations.board.unassigned')),
                TextColumn::make('deals_min_created_at')->label(__('panel.customers.started_at'))->dateTime('d.m.Y')->sortable()->extraAttributes(['class' => 'numeric-data']),
            ])
            ->filters([
                SelectFilter::make('industry')->label(__('panel.fields.industry'))->options(CompanyResource::industryOptions()),
                SelectFilter::make('city')->label(__('panel.fields.city'))->options(CompanyResource::provinceOptions())->searchable(),
            ])
            ->recordUrl(fn (Company $record): string => CompanyResource::getUrl('view', ['record' => $record]))
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomers::route('/')];
    }
}
