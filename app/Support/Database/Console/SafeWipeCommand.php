<?php

declare(strict_types=1);

namespace App\Support\Database\Console;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use Illuminate\Database\Console\WipeCommand;

final class SafeWipeCommand extends WipeCommand
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
            'db:wipe',
            is_string($connection) && $connection !== '' ? $connection : null,
        );

        return parent::handle();
    }
}
