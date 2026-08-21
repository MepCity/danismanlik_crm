<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Domain\Crm\Models\Company;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\ScopedResource;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereHas('deals')
                ->withCount('deals')
                ->withMin('deals', 'created_at'))
            ->columns([
                TextColumn::make('legal_name')->label(__('panel.fields.legal_name'))->searchable()->sortable(),
                TextColumn::make('industry')->label(__('panel.fields.industry'))->formatStateUsing(fn (string $state): string => __('panel.industries.'.$state))->sortable(),
                TextColumn::make('city')->label(__('panel.fields.city'))->sortable(),
                TextColumn::make('deals_count')->label(__('panel.customers.active_projects'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
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
