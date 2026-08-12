<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('işbirliği güçlendirmelerini PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $schema = 'collaboration_contract_'.Str::lower(Str::random(12));
    $connection = 'collaboration_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();
    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [...$configuration, 'search_path' => $schema]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('tasks', 'reminder_sent_at'))->toBeTrue()
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT count(*)
                FROM pg_trigger
                WHERE tgrelid = '{$schema}.comments'::regclass
                  AND tgname = 'comments_audit'
                  AND NOT tgisinternal
                SQL))->toBe(1)
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT count(*)
                FROM pg_trigger
                WHERE tgrelid = '{$schema}.tasks'::regclass
                  AND tgname = 'tasks_audit'
                  AND NOT tgisinternal
                SQL))->toBe(1);

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 6,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('tasks', 'reminder_sent_at'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('tasks', 'reminder_sent_at'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
