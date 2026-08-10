<?php

declare(strict_types=1);

namespace App\Domain\Program;

use Illuminate\Support\ServiceProvider;

final class ProgramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Program servis bağları ilgili iş paketlerinde tanımlanacak.
    }
}
