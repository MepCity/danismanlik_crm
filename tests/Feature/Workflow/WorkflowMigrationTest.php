<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates rolls back and remigrates the workflow schema on PostgreSQL', function (): void {
    $schema = 'workflow_contract_'.Str::lower(Str::random(12));
    $connection = 'workflow_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [
        ...$configuration,
        'search_path' => $schema,
    ]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('statuses'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('transitions'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('workflow_revisions'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('status_history'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'status_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'status'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('deals', 'status_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('deals', 'status'))->toBeFalse()
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT col_description('{$schema}.statuses'::regclass, ordinal_position)
                FROM information_schema.columns
                WHERE table_schema = '{$schema}' AND table_name = 'statuses' AND column_name = 'label'
                SQL))->toContain('çeviri dosyası istisnası')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT col_description('{$schema}.deals'::regclass, ordinal_position)
                FROM information_schema.columns
                WHERE table_schema = '{$schema}' AND table_name = 'deals' AND column_name = 'status_changed_at'
                SQL))->toContain('denormalize önbellek');

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 8,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('statuses'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('status_history'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('leads', 'status'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'status_id'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('deals', 'status'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('deals', 'status_id'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('statuses'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('status_history'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
