<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Domain\Access\Models\Team;
use App\Filament\Resources\ScopedResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
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
use Spatie\Permission\Models\Role;

/** @extends ScopedResource<User> */
final class UserResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.users';

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.users');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.user.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.user.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
            TextInput::make('email')->label(__('management.fields.email'))->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->label(__('management.fields.password'))->password()->revealable(false)
                ->required(fn (string $operation): bool => $operation === 'create')->minLength(12)->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('role_ids')->label(__('management.fields.roles'))
                ->options(Role::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->multiple()->required(),
            Select::make('team_ids')->label(__('management.fields.teams'))
                ->options(Team::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))->multiple(),
            Select::make('data_scope')->label(__('management.fields.data_scope'))->options(__('management.scopes'))->placeholder(__('management.messages.not_set')),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
            Textarea::make('change_reason')->label(__('management.fields.change_reason'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
            TextColumn::make('email')->label(__('management.fields.email'))->searchable(),
            TextColumn::make('roles.name')->label(__('management.fields.roles'))->badge(),
            TextColumn::make('teams.name')->label(__('management.fields.teams'))->badge(),
            TextColumn::make('data_scope')->label(__('management.fields.data_scope'))->formatStateUsing(fn (?string $state): string => $state === null ? __('management.messages.not_set') : __('management.scopes.'.$state)),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([EditAction::make()->label(__('management.actions.edit'))])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'create' => CreateUser::route('/olustur'), 'edit' => EditUser::route('/{record}/duzenle')];
    }
}
