<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates rolls back and remigrates the audit collaboration schema', function (): void {
    $schema = 'audit_contract_'.Str::lower(Str::random(12));
    $connection = 'audit_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [
        ...$configuration,
        'search_path' => $schema,
    ]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('activities'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('audit_log'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('comments'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('tasks'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('notifications'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('outbox'))->toBeTrue();

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 15,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('activities'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('audit_log'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('comments'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('outbox'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('audit_log'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('outbox'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
