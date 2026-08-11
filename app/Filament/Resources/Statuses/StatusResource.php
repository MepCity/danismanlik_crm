<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statuses;

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Services\OrphanTransitionInspectorContract;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Resources\ScopedResource;
use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Filament\Resources\Statuses\Pages\EditStatus;
use App\Filament\Resources\Statuses\Pages\ListStatuses;
use App\Filament\Support\OrphanImpactPresenter;
use App\Filament\Support\StatusBadge;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<Status> */
final class StatusResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.settings';

    protected static ?string $model = Status::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.statuses');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.status.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.status.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label(__('management.fields.code'))->required()->maxLength(255)->disabledOn('edit'),
            TextInput::make('label')->label(__('management.fields.name'))->required()->maxLength(255),
            Select::make('type')->label(__('management.fields.type'))->options(__('management.types'))->required()->disabledOn('edit'),
            Select::make('color')->label(__('management.fields.color'))->options(__('management.colors'))->required(),
            TextInput::make('sort_order')->label(__('management.fields.sort_order'))->integer()->minValue(0)->default(0),
            Toggle::make('is_terminal')->label(__('management.fields.terminal')),
            Hidden::make('is_active')->default(true),
            Textarea::make('change_reason')->label(__('management.fields.change_reason'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sort_order')->label(__('management.fields.sort_order'))->alignEnd()->sortable()->extraAttributes(['class' => 'numeric-data']),
            TextColumn::make('label')->label(__('management.fields.name'))->searchable()->sortable(),
            TextColumn::make('type')->label(__('management.fields.type'))->formatStateUsing(fn (string $state): string => __('management.types.'.$state)),
            TextColumn::make('color')->label(__('management.fields.color'))->formatStateUsing(
                fn (string $state, Status $record) => StatusBadge::make($state, $record->label),
            )->html(),
            IconColumn::make('is_terminal')->label(__('management.fields.terminal'))->boolean(),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([
            EditAction::make()->label(__('management.actions.edit')),
            Action::make('deactivate')->label(__('management.actions.deactivate'))->icon('heroicon-o-no-symbol')
                ->visible(fn (Status $record): bool => $record->is_active)
                ->modalDescription(fn (Status $record): string => app(OrphanImpactPresenter::class)->describe(
                    app(OrphanTransitionInspectorContract::class)->beforeStatusDeactivation($record->id),
                ))
                ->schema(fn (Status $record): array => [
                    Textarea::make('reason')->label(__('management.fields.reason'))->required(),
                    Select::make('target_status_id')->label(__('management.fields.migration_target'))
                        ->options(Status::query()->where('type', $record->type)->where('is_active', true)->whereKeyNot($record->id)->orderBy('sort_order')->pluck('label', 'id'))
                        ->required(fn (): bool => app(OrphanTransitionInspectorContract::class)->beforeStatusDeactivation($record->id)->hasOrphans())
                        ->visible(fn (): bool => app(OrphanTransitionInspectorContract::class)->beforeStatusDeactivation($record->id)->hasOrphans()),
                ])->action(function (Status $record, array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(WorkflowDeactivationService::class)->deactivateStatus(
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
        return ['index' => ListStatuses::route('/'), 'create' => CreateStatus::route('/olustur'), 'edit' => EditStatus::route('/{record}/duzenle')];
    }
}
