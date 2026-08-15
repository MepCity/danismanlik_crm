<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgramVersions;

use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\ProgramVersions\Pages\CreateProgramVersion;
use App\Filament\Resources\ProgramVersions\Pages\EditProgramVersion;
use App\Filament\Resources\ProgramVersions\Pages\ListProgramVersions;
use App\Filament\Resources\ScopedResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<ProgramVersion> */
final class ProgramVersionResource extends ScopedResource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $configurationPermission = 'program.manage';

    protected static ?string $model = ProgramVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.program_versions');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.program_version.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.program_version.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('program_id')->label(__('management.fields.program'))->relationship('program', 'name')->searchable()->preload()->required(),
            TextInput::make('call_period')->label(__('management.fields.call_period'))->required()->maxLength(255),
            DateTimePicker::make('application_opens_at')->label(__('management.fields.application_opens_at')),
            DateTimePicker::make('application_closes_at')->label(__('management.fields.application_closes_at'))->after('application_opens_at'),
            Textarea::make('description')->label(__('management.fields.description'))->columnSpanFull(),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('program.name')->label(__('management.fields.program'))->searchable()->sortable(),
            TextColumn::make('call_period')->label(__('management.fields.call_period'))->searchable(),
            TextColumn::make('application_closes_at')->label(__('management.fields.application_closes_at'))->dateTime('d.m.Y H:i')->sortable()
                ->description(fn (ProgramVersion $record): ?string => $record->application_closes_at?->isBetween(now(), now()->addDays(30)) ? __('management.messages.closing_soon') : null)
                ->color(fn (ProgramVersion $record): ?string => $record->application_closes_at?->isBetween(now(), now()->addDays(30)) ? 'warning' : null)
                ->extraAttributes(['class' => 'numeric-data']),
            TextColumn::make('doc_templates_count')->counts('docTemplates')->label(__('management.fields.template_count'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([EditAction::make()->label(__('management.actions.edit'))])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramVersions::route('/'),
            'create' => CreateProgramVersion::route('/olustur'),
            'edit' => EditProgramVersion::route('/{record}/duzenle'),
        ];
    }
}
