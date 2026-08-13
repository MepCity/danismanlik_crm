<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('migrates rolls back and remigrates the checklist suggestion schema', function (): void {
    $schema = 'checklist_contract_'.Str::lower(Str::random(12));
    $connection = 'checklist_contract';
    $configuration = config('database.connections.testing');

    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [
        ...$configuration,
        'search_path' => $schema,
    ]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('document_requirement_suggestions'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('deal_documents', 'condition_matches'))->toBeTrue();

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 11,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('document_requirement_suggestions'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('deal_documents', 'condition_matches'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasTable('document_requirement_suggestions'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
