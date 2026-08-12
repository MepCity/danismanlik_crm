<?php

declare(strict_types=1);

namespace App\Support\Database\Console;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use Illuminate\Database\Console\Migrations\RefreshCommand;

final class SafeRefreshCommand extends RefreshCommand
{
    public function __construct(
        private readonly DestructiveDatabaseCommandGuard $guard,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = $this->option('database');
        $this->guard->ensureTestingDatabaseIsSafe(
            'migrate:refresh',
            is_string($connection) && $connection !== '' ? $connection : null,
        );

        return parent::handle();
    }
}
