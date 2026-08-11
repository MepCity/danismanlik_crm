<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates rolls back and remigrates the program deal schema on PostgreSQL', function (): void {
    $schema = 'program_deal_contract_'.Str::lower(Str::random(12));
    $connection = 'program_deal_contract';
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
            ->and(Schema::connection($connection)->hasTable('programs'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('program_versions'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('doc_templates'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('deals'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('deal_documents'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('files'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'interested_program_version_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'deal_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'subject_type'))->toBeFalse()
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT data_type
                FROM information_schema.columns
                WHERE table_schema = '{$schema}'
                  AND table_name = 'files'
                  AND column_name = 'storage_key'
                SQL))->toBe('uuid')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT col_description('{$schema}.files'::regclass, ordinal_position)
                FROM information_schema.columns
                WHERE table_schema = '{$schema}'
                  AND table_name = 'files'
                  AND column_name = 'storage_key'
                SQL))->toContain('deal_id')
            ->and(DB::connection($connection)->scalar(<<<SQL
                SELECT obj_description('{$schema}.program_versions'::regclass)
                SQL))->toContain('sözleşmesel anlık görüntü');

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 1,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('programs'))->toBeFalse()
            ->and(Schema::connection($connection)->hasTable('deals'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('leads', 'interested_program'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'subject_type'))->toBeTrue();

        expect(Artisan::call('migrate', [
            '--database' => $connection,
            '--force' => true,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('programs'))->toBeTrue()
            ->and(Schema::connection($connection)->hasTable('files'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
