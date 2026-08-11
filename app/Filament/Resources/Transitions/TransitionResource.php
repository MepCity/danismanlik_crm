<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transitions;

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Services\OrphanTransitionInspectorContract;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Forms\ConditionEditor;
use App\Filament\Resources\ScopedResource;
use App\Filament\Resources\Transitions\Pages\CreateTransition;
use App\Filament\Resources\Transitions\Pages\EditTransition;
use App\Filament\Resources\Transitions\Pages\ListTransitions;
use App\Filament\Support\OrphanImpactPresenter;
use App\Models\User;
use App\Support\Conditions\ConditionDefinition;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;

/** @extends ScopedResource<Transition> */
final class TransitionResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.settings';

    protected static ?string $model = Transition::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 21;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.transitions');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.transition.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.transition.plural');
    }

    public static function form(Schema $schema): Schema
    {
        $statusOptions = Status::query()->orderBy('type')->orderBy('sort_order')->get()->mapWithKeys(
            fn (Status $status): array => [$status->id => __('management.types.'.$status->type).' · '.$status->label],
        );

        return $schema->components([
            Select::make('from_status_id')->label(__('management.fields.from_status'))->options($statusOptions)->searchable()->required()->live(),
            Select::make('to_status_id')->label(__('management.fields.to_status'))->options($statusOptions)->searchable()->required()->different('from_status_id'),
            Select::make('required_permission')->label(__('management.fields.required_permission'))
                ->options(Permission::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name'))->searchable(),
            ...ConditionEditor::schema(),
            Hidden::make('is_active')->default(true),
            Textarea::make('change_reason')->label(__('management.fields.change_reason'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('fromStatus.label')->label(__('management.fields.from_status'))->searchable()->sortable(),
            TextColumn::make('toStatus.label')->label(__('management.fields.to_status'))->searchable()->sortable(),
            TextColumn::make('required_permission')->label(__('management.fields.required_permission'))->placeholder(__('management.messages.not_set')),
            TextColumn::make('condition')->label(__('management.fields.condition_preview'))
                ->formatStateUsing(fn (mixed $state): string => ConditionDefinition::preview(is_array($state) ? $state : null))->limit(70),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([
            EditAction::make()->label(__('management.actions.edit')),
            Action::make('deactivate')->label(__('management.actions.deactivate'))->icon('heroicon-o-no-symbol')
                ->visible(fn (Transition $record): bool => $record->is_active)
                ->modalDescription(fn (Transition $record): string => app(OrphanImpactPresenter::class)->describe(
                    app(OrphanTransitionInspectorContract::class)->beforeTransitionDeactivation($record->id),
                ))
                ->schema(fn (Transition $record): array => [
                    Textarea::make('reason')->label(__('management.fields.reason'))->required(),
                    Select::make('target_status_id')->label(__('management.fields.migration_target'))
                        ->options(Status::query()->where('type', $record->fromStatus->type)->where('is_active', true)->whereKeyNot($record->from_status_id)->orderBy('sort_order')->pluck('label', 'id'))
                        ->required(fn (): bool => app(OrphanTransitionInspectorContract::class)->beforeTransitionDeactivation($record->id)->hasOrphans())
                        ->visible(fn (): bool => app(OrphanTransitionInspectorContract::class)->beforeTransitionDeactivation($record->id)->hasOrphans()),
                ])->action(function (Transition $record, array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(WorkflowDeactivationService::class)->deactivateTransition(
                        $record->id,
                        $actor->id,
                        (string) $data['reason'],
                        isset($data['target_status_id']) ? (int) $data['target_status_id'] : null,
                    );
                    Notification::make()->success()->title(__('management.messages.deactivated'))->send();
                }),
        ])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTransitions::route('/'), 'create' => CreateTransition::route('/olustur'), 'edit' => EditTransition::route('/{record}/duzenle')];
    }
}
