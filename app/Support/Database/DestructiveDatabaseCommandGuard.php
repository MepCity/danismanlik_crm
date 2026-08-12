<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final readonly class DestructiveDatabaseCommandGuard
{
    private const array DESTRUCTIVE_COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
    ];

    private const string TEST_DATABASE = 'tesvik_crm_test';

    public function __construct(
        private Application $app,
        private Repository $config,
    ) {}

    public function ensureTestingDatabaseIsSafe(string $command, ?string $connection = null): void
    {
        if (! in_array($command, self::DESTRUCTIVE_COMMANDS, true)
            || ! $this->app->environment('testing')) {
            return;
        }

        $connection ??= $this->config->string('database.default');
        $database = $this->config->get("database.connections.{$connection}.database");
        $databaseName = is_string($database) && $database !== '' ? $database : __('database_safety.unknown_database');

        if ($databaseName !== self::TEST_DATABASE) {
            throw new RuntimeException(__('database_safety.testing_target_rejected', [
                'database' => $databaseName,
            ]));
        }
    }
}
