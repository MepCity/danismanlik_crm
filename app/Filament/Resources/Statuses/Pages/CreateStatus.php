<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statuses\Pages;

use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Resources\Statuses\StatusResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateStatus extends CreateRecord
{
    protected static string $resource = StatusResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $reason = (string) $data['change_reason'];
        unset($data['change_reason']);

        return app(WorkflowDeactivationService::class)->createStatus($data, $actor->id, $reason);
    }
}
