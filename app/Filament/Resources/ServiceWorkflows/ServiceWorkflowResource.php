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
                ->extraAttributes(['class' => 'cubicl-workflow-form-section cubicl-workflow-form-section--identity'])
                ->compact()
                ->schema([
                    TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255)->columnSpan(4),
                    Toggle::make('is_active')->label(__('management.fields.active'))->default(true)->inline(false)->columnSpan(2),
                    Textarea::make('description')->label(__('management.fields.description'))->rows(2)->columnSpanFull(),
                ])->columns(6),
            Section::make(__('management.workflow_setup.sections.steps.title'))
                ->description(__('management.workflow_setup.sections.steps.description'))
                ->extraAttributes(['class' => 'cubicl-workflow-form-section cubicl-workflow-form-section--steps'])
                ->compact()
                ->schema([
                    Repeater::make('steps')
                        ->hiddenLabel()
                        ->extraAttributes(['class' => 'cubicl-workflow-steps'])
                        ->schema([
                            Hidden::make('id'),
                            Select::make('type')->label(__('management.fields.step_type'))->options(__('management.workflow_step_types'))->required()->default('action')->columnSpan(2),
                            TextInput::make('title')->label(__('management.fields.step_title'))->required()->maxLength(255)->columnSpan(4),
                            Textarea::make('guidance')->label(__('management.fields.step_guidance'))->required()->rows(2)->columnSpanFull(),
                            Textarea::make('attention_note')->label(__('management.fields.attention_note'))->rows(2)->columnSpanFull(),
                        ])
                        ->columns(6)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel(__('management.workflow_setup.add_step'))
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : __('management.workflow_setup.new_step'))
                        ->collapsible()
                        // Saved steps stay compact; only an unsaved draft opens.
                        ->collapsed(fn (?Schema $item = null): bool => filled($item?->getRawState()['id'] ?? null))
                        ->cloneable()
                        ->reorderable(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['steps' => fn (Builder $step): Builder => $step->where('is_active', true), 'programVersions']))
            ->emptyStateHeading(__('management.workflow_setup.empty.heading'))
            ->emptyStateDescription(__('management.workflow_setup.empty.description'))
            ->emptyStateIcon('heroicon-o-queue-list')
            ->columns([
                TextColumn::make('name')
                    ->label(__('management.fields.name'))
                    ->description(fn (ServiceWorkflow $record): string => filled($record->description) ? (string) str($record->description)->limit(90) : __('management.messages.not_set'))
                    ->searchable()
                    ->sortable(),
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
