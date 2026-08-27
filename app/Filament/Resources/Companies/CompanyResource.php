<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Domain\Crm\Models\Company;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\ScopedResource;
use App\Filament\Support\CompanyOpportunityAction;
use App\Filament\Support\CustomerFlowAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panel.company_directory.identity'))
                ->description(__('panel.company_directory.identity_help'))
                ->schema([
                    TextInput::make('legal_name')->label(__('panel.fields.legal_name'))->required()->maxLength(255),
                    Select::make('industry')->label(__('panel.fields.industry'))->options(self::industryOptions())->searchable()->required(),
                    Select::make('city')->label(__('panel.fields.city'))->options(self::provinceOptions())->searchable(),
                    TextInput::make('district')->label(__('panel.fields.district'))->maxLength(255),
                ])->columns(2),
            Section::make(__('panel.company_directory.commercial'))
                ->description(__('panel.company_directory.commercial_help'))
                ->collapsed()
                ->schema([
                    TextInput::make('tax_number')->label(__('panel.fields.tax_number'))->maxLength(11)->rule('regex:/^[0-9]{10}([0-9])?$/'),
                    TextInput::make('tax_office')->label(__('panel.fields.tax_office'))->maxLength(255),
                    TextInput::make('nace_code')->label(__('panel.fields.nace_code'))->maxLength(255),
                    Select::make('size')->label(__('panel.fields.size'))->options(__('panel.company_directory.sizes')),
                    TextInput::make('employee_count')->label(__('panel.fields.employee_count'))->integer()->minValue(0),
                    Toggle::make('is_active')->label(__('panel.fields.status'))->default(true),
                ])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('owner')->withCount(['contacts', 'leads', 'deals']))
            ->columns([
                TextColumn::make('legal_name')->label(__('panel.fields.legal_name'))->searchable()->sortable(),
                TextColumn::make('industry')->label(__('panel.fields.industry'))->formatStateUsing(fn (string $state): string => __('panel.industries.'.$state))->sortable(),
                TextColumn::make('city')->label(__('panel.fields.city'))->sortable(),
                TextColumn::make('owner.name')->label(__('panel.fields.owner'))->placeholder('—')->toggleable(),
                TextColumn::make('contacts_count')->label(__('panel.company_directory.contacts'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                TextColumn::make('leads_count')->label(__('panel.company_directory.opportunities'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                TextColumn::make('deals_count')->label(__('panel.company_directory.projects'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                IconColumn::make('is_active')->label(__('panel.fields.status'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('industry')->label(__('panel.fields.industry'))->options(self::industryOptions()),
                SelectFilter::make('city')->label(__('panel.fields.city'))->options(self::provinceOptions())->searchable(),
                SelectFilter::make('size')->label(__('panel.fields.size'))->options(__('panel.company_directory.sizes')),
                TernaryFilter::make('is_active')->label(__('panel.fields.status')),
                TernaryFilter::make('customer_state')
                    ->label(__('panel.company_directory.customer_state'))
                    ->trueLabel(__('panel.company_directory.has_customer_flow'))
                    ->falseLabel(__('panel.company_directory.directory_only'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('deals'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('deals'),
                    ),
            ])
            ->recordActions([
                CompanyOpportunityAction::make(),
                CustomerFlowAction::forRecord(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->recordUrl(fn (Company $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/olustur'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/duzenle'),
        ];
    }

    /** @return array<string, string> */
    public static function industryOptions(): array
    {
        /** @var list<string> $industries */
        $industries = config('operations.company_industries', []);
        $options = [];

        foreach ($industries as $industry) {
            $options[$industry] = __('panel.industries.'.$industry);
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function provinceOptions(): array
    {
        /** @var list<string> $provinces */
        $provinces = config('turkey.provinces', []);

        return array_combine($provinces, $provinces);
    }
}
