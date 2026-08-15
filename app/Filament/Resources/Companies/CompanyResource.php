<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Domain\Crm\Models\Company;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\ScopedResource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<Company> */
final class CompanyResource extends ScopedResource
{
    protected static ?string $model = Company::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.crm');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.resources.companies.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('panel.resources.companies.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resources.companies.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legal_name')->label(__('panel.fields.legal_name'))->searchable()->sortable(),
                TextColumn::make('city')->label(__('panel.fields.city'))->sortable(),
                IconColumn::make('is_active')->label(__('panel.fields.status'))->boolean(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->recordUrl(fn (Company $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'view' => ViewCompany::route('/{record}'),
        ];
    }
}
