<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs;

use App\Domain\Program\Models\Program;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\ScopedResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<Program> */
final class ProgramResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'program.manage';

    protected static ?string $model = Program::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.programs');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.program.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.program.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
            Select::make('institution')->label(__('management.fields.institution'))->options(__('management.institutions'))->required(),
            TextInput::make('code')->label(__('management.fields.code'))->required()->unique(ignoreRecord: true)->maxLength(255),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
            TextColumn::make('institution')->label(__('management.fields.institution'))->formatStateUsing(fn (string $state): string => __('management.institutions.'.$state)),
            TextColumn::make('code')->label(__('management.fields.code'))->extraAttributes(['class' => 'numeric-data']),
            TextColumn::make('versions_count')->counts('versions')->label(__('management.models.program_version.plural'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([EditAction::make()->label(__('management.actions.edit'))])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrograms::route('/'),
            'create' => CreateProgram::route('/olustur'),
            'edit' => EditProgram::route('/{record}/duzenle'),
        ];
    }
}
