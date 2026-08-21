<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Domain\Crm\Actions\SaveEmailTemplate;
use App\Domain\Crm\Models\EmailTemplate;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof EmailTemplate, 404);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(SaveEmailTemplate::class)->execute($record, $data, $actor);
    }
}
