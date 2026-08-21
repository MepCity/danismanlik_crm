<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceWorkflows;

use App\Domain\Program\Models\ServiceWorkflow;
use App\Filament\Resources\ScopedResource;
use App\Filament\Resources\ServiceWorkflows\Pages\CreateServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\Pages\EditServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\Pages\ListServiceWorkflows;
use Filament\Actions\EditAction;
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

/** @extends ScopedResource<ServiceWorkflow> */
final class ServiceWorkflowResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'program.manage';

    protected static ?string $model = ServiceWorkflow::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.service_workflows');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.service_workflow.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.service_workflow.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('management.workflow_setup.sections.identity.title'))
                ->description(__('management.workflow_setup.sections.identity.description'))
                ->schema([
                    TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
                    Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
                    Textarea::make('description')->label(__('management.fields.description'))->rows(3)->columnSpanFull(),
                ])->columns(2),
            Section::make(__('management.workflow_setup.sections.steps.title'))
                ->description(__('management.workflow_setup.sections.steps.description'))
                ->schema([
                    Repeater::make('steps')
                        ->hiddenLabel()
                        ->schema([
                            Hidden::make('id'),
                            Select::make('type')->label(__('management.fields.step_type'))->options(__('management.workflow_step_types'))->required()->default('action')->columnSpan(2),
                            TextInput::make('title')->label(__('management.fields.step_title'))->required()->maxLength(255)->columnSpan(4),
                            Textarea::make('guidance')->label(__('management.fields.step_guidance'))->required()->rows(3)->columnSpanFull(),
                            Textarea::make('attention_note')->label(__('management.fields.attention_note'))->rows(2)->columnSpanFull(),
                        ])
                        ->columns(6)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel(__('management.workflow_setup.add_step'))
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : __('management.workflow_setup.new_step'))
                        ->reorderable(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['steps' => fn (Builder $step): Builder => $step->where('is_active', true), 'programVersions']))
            ->columns([
                TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
                TextColumn::make('description')->label(__('management.fields.description'))->limit(70)->placeholder(__('management.messages.not_set')),
                TextColumn::make('steps_count')->label(__('management.fields.step_count'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                TextColumn::make('program_versions_count')->label(__('management.fields.linked_services'))->alignEnd()->extraAttributes(['class' => 'numeric-data']),
                IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
            ])
            ->recordActions([EditAction::make()->label(__('management.actions.edit'))])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceWorkflows::route('/'),
            'create' => CreateServiceWorkflow::route('/olustur'),
            'edit' => EditServiceWorkflow::route('/{record}/duzenle'),
        ];
    }
}
