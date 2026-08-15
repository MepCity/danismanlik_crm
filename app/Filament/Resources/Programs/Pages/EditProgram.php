<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Pages;

use App\Domain\Program\Actions\SaveProgramConfiguration;
use App\Domain\Program\Models\Program;
use App\Filament\Resources\Programs\ProgramResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditProgram extends EditRecord
{
    protected static string $resource = ProgramResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Program $program */
        $program = $this->record;
        $version = $program->versions()->with(['docTemplates' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])->latest('id')->first();

        return [
            ...$data,
            'call_period' => $version?->call_period,
            'application_opens_at' => $version?->application_opens_at?->toDateString(),
            'application_closes_at' => $version?->application_closes_at?->toDateString(),
            'description' => $version?->description,
            'documents' => $version?->docTemplates->map(fn ($template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_required' => $template->is_required,
                'accepted_formats' => $template->accepted_formats,
                'validity_days' => $template->validity_days,
            ])->values()->all() ?: [[
                'name' => '',
                'is_required' => true,
                'accepted_formats' => ['pdf'],
            ]],
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Program, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveProgramConfiguration::class)->execute($record, $data, $actor);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('management.program_setup.messages.updated');
    }
}
