<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('hizmet iş akışı şemasını PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $schema = 'service_workflow_contract_'.Str::lower(Str::random(12));
    $connection = 'service_workflow_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [
        ...$configuration,
        'search_path' => $schema,
    ]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('service_workflows'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('service_workflow_steps'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('program_versions', 'service_workflow_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('program_versions', 'workflow_snapshot'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('deals', 'workflow_snapshot'))->toBeTrue();

        expect(Artisan::call('migrate:rollback', ['--database' => $connection, '--force' => true, '--step' => 6]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('service_workflows'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('program_versions', 'workflow_snapshot'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('deals', 'workflow_snapshot'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('service_workflow_steps'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
