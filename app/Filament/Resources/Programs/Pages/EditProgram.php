<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Resources\Pages\EditRecord;

final class EditProgram extends EditRecord
{
    protected static string $resource = ProgramResource::class;
}
