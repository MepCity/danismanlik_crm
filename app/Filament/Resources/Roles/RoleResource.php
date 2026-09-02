<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\ScopedResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @extends ScopedResource<Role> */
final class RoleResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.roles';

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 32;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.roles');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.role.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.role.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('management.fields.name'))->required()->disabledOn('edit'),
            Select::make('default_scope')->label(__('management.fields.default_scope'))->options(__('management.scopes'))->required(),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
            CheckboxList::make('permission_ids')
                ->label(__('management.fields.permissions'))
                ->options(Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'id'))
                ->columns(2)
                ->searchable(),
            Textarea::make('change_reason')->label(__('management.fields.change_reason'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
            TextColumn::make('default_scope')->label(__('management.fields.default_scope'))->formatStateUsing(
                fn (?string $state): string => $state === null ? __('management.messages.not_set') : __('management.scopes.'.$state),
            ),
            TextColumn::make('permissions_count')->counts('permissions')->label(__('management.fields.permissions_count'))->alignEnd(),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([
            EditAction::make()->label(__('management.actions.edit')),
        ])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'edit' => EditRole::route('/{record}/duzenle'),
        ];
    }
}
