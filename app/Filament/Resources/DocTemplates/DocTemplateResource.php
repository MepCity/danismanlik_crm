<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocTemplates;

use App\Domain\Program\Models\DocTemplate;
use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\DocTemplates\Pages\CreateDocTemplate;
use App\Filament\Resources\DocTemplates\Pages\EditDocTemplate;
use App\Filament\Resources\DocTemplates\Pages\ListDocTemplates;
use App\Filament\Resources\ScopedResource;
use App\Support\Conditions\ConditionDefinition;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<DocTemplate> */
final class DocTemplateResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'program.manage';

    protected static ?string $model = DocTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.document_templates');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.document_template.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.document_template.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('program_version_id')->label(__('management.models.program_version.singular'))
                ->relationship('programVersion', 'call_period')->searchable()->preload()->required(),
            TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
            Textarea::make('description')->label(__('management.fields.description'))->columnSpanFull(),
            Toggle::make('is_required')->label(__('management.fields.required'))->default(true),
            Select::make('accepted_formats')->label(__('management.fields.accepted_formats'))->options(__('management.formats'))->multiple()->required(),
            TextInput::make('validity_days')->label(__('management.fields.validity_days'))->integer()->minValue(1),
            TextInput::make('sort_order')->label(__('management.fields.sort_order'))->integer()->minValue(0)->default(0),
            ...ConditionEditor::schema(),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('programVersion.program.name')->label(__('management.fields.program'))->searchable(),
            TextColumn::make('programVersion.call_period')->label(__('management.fields.call_period')),
            TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
            IconColumn::make('is_required')->label(__('management.fields.required'))->boolean(),
            TextColumn::make('condition')->label(__('management.fields.condition_preview'))
                ->formatStateUsing(fn (mixed $state): string => ConditionDefinition::preview(is_array($state) ? $state : null))->limit(70),
            TextColumn::make('sort_order')->label(__('management.fields.sort_order'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([EditAction::make()])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocTemplates::route('/'),
            'create' => CreateDocTemplate::route('/olustur'),
            'edit' => EditDocTemplate::route('/{record}/duzenle'),
        ];
    }
}
