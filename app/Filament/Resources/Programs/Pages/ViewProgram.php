<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Pages;

use App\Domain\Program\Actions\StartProgram;
use App\Domain\Program\Models\Program;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

final class ViewProgram extends ViewRecord
{
    protected static string $resource = ProgramResource::class;

    protected string $view = 'filament.resources.programs.view-program';

    public string $activeTab = 'workflow';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label(__('management.program_start.action'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn (): bool => ! $this->program()->is_active)
                ->action(function (StartProgram $starter): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);

                    try {
                        $starter->execute($this->program(), $actor);
                        Notification::make()->title(__('management.program_start.started'))->success()->send();
                        $this->record->refresh();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(__('management.program_start.blocked'))
                            ->body(implode("\n", $exception->validator->errors()->all()))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            EditAction::make()->label(__('management.actions.edit')),
        ];
    }

    private function program(): Program
    {
        abort_unless($this->record instanceof Program, 404);

        return $this->record;
    }
}
