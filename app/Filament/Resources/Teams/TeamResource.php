<?php

declare(strict_types=1);

namespace App\Filament\Resources\Teams;

use App\Domain\Access\Models\Team;
use App\Filament\Resources\ScopedResource;
use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
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

/** @extends ScopedResource<Team> */
final class TeamResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.users';

    protected static ?string $model = Team::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('management.navigation.teams');
    }

    public static function getModelLabel(): string
    {
        return __('management.models.team.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('management.models.team.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('management.fields.name'))->required()->maxLength(255),
            Select::make('manager_id')->label(__('management.fields.team_manager'))
                ->options(User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable(),
            Select::make('member_ids')->label(__('management.fields.team_members'))
                ->options(User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->multiple()
                ->searchable(),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
            Textarea::make('change_reason')->label(__('management.fields.change_reason'))->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('management.fields.name'))->searchable()->sortable(),
            TextColumn::make('manager.name')->label(__('management.fields.team_manager'))->searchable()->sortable(),
            TextColumn::make('members.name')->label(__('management.fields.team_members'))->badge(),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([
            EditAction::make()->label(__('management.actions.edit')),
        ])->defaultPaginationPageOption(25)->paginationPageOptions([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/olustur'),
            'edit' => EditTeam::route('/{record}/duzenle'),
        ];
    }
}
