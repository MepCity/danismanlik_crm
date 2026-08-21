<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates rolls back and remigrates the CRM schema on PostgreSQL', function (): void {
    $schema = 'crm_migration_contract_'.Str::lower(Str::random(12));
    $connection = 'crm_migration_contract';
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
            ->and(Schema::connection($connection)->hasTable('companies'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('contacts'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'do_not_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('communication_consents'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('leads'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('interactions'))->toBeTrue()
            ->and(DB::connection($connection)->table('information_schema.columns')
                ->where('table_schema', $schema)
                ->where('table_name', 'companies')
                ->where('column_name', 'id')
                ->value('is_identity'))->toBe('YES')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT indexdef
                FROM pg_indexes
                WHERE schemaname = '{$schema}'
                  AND indexname = 'communication_consents_current_lookup'
                SQL))->toContain('effective_from DESC')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT indexdef
                FROM pg_indexes
                WHERE schemaname = '{$schema}'
                  AND indexname = 'interactions_lead_timeline'
                SQL))->toContain('occurred_at DESC')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT indexdef
                FROM pg_indexes
                WHERE schemaname = '{$schema}'
                  AND indexname = 'interactions_deal_timeline'
                SQL))->toContain('occurred_at DESC');

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('companies'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('communication_consents'))->toBeFalse();

        expect(Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('companies'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('interactions'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
