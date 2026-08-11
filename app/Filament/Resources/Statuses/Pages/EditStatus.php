<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statuses\Pages;

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Services\WorkflowDeactivationService;
use App\Filament\Resources\Statuses\StatusResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditStatus extends EditRecord
{
    protected static string $resource = StatusResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Status, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $reason = (string) $data['change_reason'];
        unset($data['change_reason']);

        return app(WorkflowDeactivationService::class)->updateStatus($record, $data, $actor->id, $reason);
    }
}
