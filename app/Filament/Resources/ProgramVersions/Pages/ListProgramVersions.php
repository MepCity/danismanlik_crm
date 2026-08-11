<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgramVersions\Pages;

use App\Domain\Program\Actions\CopyProgramVersion;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\ProgramVersions\ProgramVersionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListProgramVersions extends ListRecords
{
    protected static string $resource = ProgramVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('management.actions.create')),
            Action::make('copy_previous')
                ->label(__('management.actions.copy_previous'))
                ->icon('heroicon-o-document-duplicate')
                ->schema([
                    Select::make('source_id')->label(__('management.models.program_version.singular'))
                        ->options(ProgramVersion::query()->with('program')->latest('id')->get()->mapWithKeys(
                            fn (ProgramVersion $version): array => [$version->id => $version->program->name.' · '.$version->call_period],
                        ))->searchable()->required(),
                    TextInput::make('call_period')->label(__('management.fields.call_period'))->required(),
                    DateTimePicker::make('application_opens_at')->label(__('management.fields.application_opens_at')),
                    DateTimePicker::make('application_closes_at')->label(__('management.fields.application_closes_at'))->after('application_opens_at'),
                    Textarea::make('description')->label(__('management.fields.description')),
                ])
                ->modalDescription(__('management.actions.copy_previous_help'))
                ->action(function (array $data): void {
                    $source = ProgramVersion::query()->findOrFail((int) $data['source_id']);
                    unset($data['source_id']);
                    app(CopyProgramVersion::class)->execute($source, [...$data, 'is_active' => true]);
                    Notification::make()->success()->title(__('management.messages.copied'))->send();
                }),
        ];
    }
}
