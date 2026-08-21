<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates the access schema up and down on PostgreSQL', function (): void {
    $schema = 'migration_contract_'.Str::lower(Str::random(12));
    $connection = 'migration_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [
        ...$configuration,
        'search_path' => $schema,
    ]);

    try {
        expect(Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('users'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('roles'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('teams'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('role_permission_history'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('break_glass_grants'))->toBeFalse()
            ->and(DB::connection($connection)->table('permissions')->where('name', 'page.access_management')->where('is_active', true)->exists())->toBeTrue()
            ->and(DB::connection($connection)->table('information_schema.columns')
                ->where('table_schema', $schema)
                ->where('table_name', 'teams')
                ->where('column_name', 'id')
                ->value('is_identity'))->toBe('YES');

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 1,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('break_glass_grants'))->toBeTrue()
            ->and(DB::connection($connection)->table('permissions')->where('name', 'page.access_management')->where('is_active', false)->exists())->toBeTrue();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('break_glass_grants'))->toBeFalse();

        expect(Artisan::call('migrate:reset', [
            '--database' => $connection,
            '--force' => true,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('users'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('roles'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('teams'))->toBeFalse();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
