<?php

declare(strict_types=1);

namespace App\Filament\Resources\BreakGlassGrants;

use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Services\BreakGlassService;
use App\Filament\Resources\BreakGlassGrants\Pages\ListBreakGlassGrants;
use App\Filament\Resources\ScopedResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<BreakGlassGrant> */
final class BreakGlassGrantResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'access.break_glass.grant';

    protected static ?string $model = BreakGlassGrant::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 32;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.break_glass');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.break_glass.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.break_glass.plural');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label(__('management.fields.user'))->searchable(),
            TextColumn::make('grantedBy.name')->label(__('management.fields.granted_by')),
            TextColumn::make('reason')->label(__('management.fields.reason'))->limit(60),
            TextColumn::make('expires_at')->label(__('management.fields.expires_at'))->dateTime('d.m.Y H:i')->extraAttributes(['class' => 'numeric-data']),
            TextColumn::make('revoked_at')->label(__('management.fields.status'))->formatStateUsing(
                fn (mixed $state, BreakGlassGrant $record): string => $state !== null
                    ? __('management.messages.revoked')
                    : ($record->expires_at->isPast() ? __('management.messages.expired') : __('management.messages.active')),
            ),
        ])->headerActions([
            Action::make('grant')->label(__('management.actions.grant'))->icon('heroicon-o-key')
                ->schema([
                    Select::make('user_id')->label(__('management.fields.user'))
                        ->options(User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required(),
                    Textarea::make('reason')->label(__('management.fields.reason'))->required(),
                    TextInput::make('duration_minutes')->label(__('management.fields.duration_minutes'))->integer()->minValue(1)->maxValue(60)->default(30)->required(),
                ])->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $target = User::query()->findOrFail((int) $data['user_id']);
                    app(BreakGlassService::class)->grant($target, $actor, (string) $data['reason'], now()->addMinutes((int) $data['duration_minutes']));
                    Notification::make()->success()->title(__('management.messages.granted'))->send();
                }),
        ])->recordActions([
            Action::make('revoke')->label(__('management.actions.revoke'))->icon('heroicon-o-x-mark')
                ->visible(fn (BreakGlassGrant $record): bool => $record->revoked_at === null && $record->expires_at->isFuture())
                ->requiresConfirmation()
                ->action(function (BreakGlassGrant $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(BreakGlassService::class)->revoke($record, $actor);
                    Notification::make()->success()->title(__('management.messages.revoked'))->send();
                }),
        ])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBreakGlassGrants::route('/')];
    }
}
