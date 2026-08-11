<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgramVersions\Pages;

use App\Filament\Resources\ProgramVersions\ProgramVersionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateProgramVersion extends CreateRecord
{
    protected static string $resource = ProgramVersionResource::class;
}
