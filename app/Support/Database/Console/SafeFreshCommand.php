<?php

declare(strict_types=1);

namespace App\Support\Database\Console;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Migrations\Migrator;

final class SafeFreshCommand extends FreshCommand
{
    public function __construct(
        Migrator $migrator,
        private readonly DestructiveDatabaseCommandGuard $guard,
    ) {
        parent::__construct($migrator);
    }

    public function handle(): int
    {
        $connection = $this->option('database');
        $this->guard->ensureTestingDatabaseIsSafe(
            'migrate:fresh',
            is_string($connection) && $connection !== '' ? $connection : null,
        );

        return parent::handle();
    }
}
