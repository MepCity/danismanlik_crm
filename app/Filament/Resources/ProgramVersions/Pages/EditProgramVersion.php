<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgramVersions\Pages;

use App\Filament\Resources\ProgramVersions\ProgramVersionResource;
use Filament\Resources\Pages\EditRecord;

final class EditProgramVersion extends EditRecord
{
    protected static string $resource = ProgramVersionResource::class;
}
