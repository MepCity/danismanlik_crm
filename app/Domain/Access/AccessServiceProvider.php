<?php

declare(strict_types=1);

namespace App\Domain\Access;

use Illuminate\Support\ServiceProvider;

final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Concrete authorization services are auto-wired by the container.
    }
}
