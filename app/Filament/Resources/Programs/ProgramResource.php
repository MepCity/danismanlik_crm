<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs;

use App\Domain\Program\Models\Program;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\ScopedResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            Section::make(__('management.program_setup.sections.program.title'))
                ->description(__('management.program_setup.sections.program.description'))
                ->schema([
                    TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
                    Select::make('institution')->label(__('management.fields.institution'))->options(__('management.institutions'))->required(),
                    Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
                ])->columns(2),
            Section::make(__('management.program_setup.sections.period.title'))
                ->description(__('management.program_setup.sections.period.description'))
                ->schema([
                    TextInput::make('call_period')->label(__('management.fields.call_period'))->required()->maxLength(255),
                    DatePicker::make('application_opens_at')->label(__('management.fields.application_opens_at'))->native(false),
                    DatePicker::make('application_closes_at')->label(__('management.fields.application_closes_at'))->native(false)->after('application_opens_at'),
                    Textarea::make('description')->label(__('management.fields.description'))->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make(__('management.program_setup.sections.documents.title'))
                ->description(__('management.program_setup.sections.documents.description'))
                ->schema([
                    Repeater::make('documents')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('name')->label(__('management.fields.document_name'))->required()->maxLength(255)->columnSpan(3),
                            Toggle::make('is_required')->label(__('management.fields.required'))->default(true)->columnSpan(1),
                            Select::make('accepted_formats')->label(__('management.fields.accepted_formats'))->options(__('management.formats'))->multiple()->required()->default(['pdf'])->columnSpan(2),
                            TextInput::make('validity_days')->label(__('management.fields.validity_days'))->integer()->minValue(1)->columnSpan(2),
                            Textarea::make('description')->label(__('management.fields.document_description'))->rows(2)->columnSpan(4),
                        ])
                        ->columns(6)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel(__('management.program_setup.add_document'))
                        ->itemLabel(fn (array $state): string => filled($state['name'] ?? null)
                            ? (string) $state['name']
                            : __('management.program_setup.new_document'))
                        ->reorderable(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['latestVersion.docTemplates']))
            ->columns([
                TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
                TextColumn::make('institution')->label(__('management.fields.institution'))->formatStateUsing(fn (string $state): string => __('management.institutions.'.$state)),
                TextColumn::make('latestVersion.call_period')->label(__('management.fields.call_period'))->placeholder(__('management.messages.not_set')),
                TextColumn::make('application_period')
                    ->label(__('management.fields.application_period'))
                    ->state(fn (Program $record): string => self::applicationPeriod($record))
                    ->extraAttributes(['class' => 'numeric-data']),
                TextColumn::make('document_count')
                    ->label(__('management.fields.template_count'))
                    ->state(fn (Program $record): int => $record->latestVersion?->docTemplates->where('is_active', true)->count() ?? 0)
                    ->alignEnd()
                    ->extraAttributes(['class' => 'numeric-data']),
                IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
            ])->recordActions([EditAction::make()->label(__('management.actions.edit'))])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    private static function applicationPeriod(Program $program): string
    {
        $version = $program->latestVersion;
        $opensAt = $version?->application_opens_at?->format('d.m.Y');
        $closesAt = $version?->application_closes_at?->format('d.m.Y');

        if ($opensAt !== null && $closesAt !== null) {
            return __('management.program_setup.period_range', ['start' => $opensAt, 'end' => $closesAt]);
        }

        return $opensAt ?? $closesAt ?? __('management.messages.not_set');
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
