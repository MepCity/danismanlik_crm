<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('firma fırsat genişletmesini PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $schema = 'prospect_contract_'.Str::lower(Str::random(12));
    $connection = 'prospect_contract';
    $configuration = config('database.connections.testing');
    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [...$configuration, 'search_path' => $schema]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('leads', 'primary_contact_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'contact_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('comments', 'company_id'))->toBeTrue();

        $rollbackSteps = DB::connection($connection)->table('migrations')
            ->where('migration', '>=', '2026_08_19_090000_expand_crm_intake_and_company_timeline')
            ->count();
        expect(Artisan::call('migrate:rollback', ['--database' => $connection, '--force' => true, '--step' => $rollbackSteps]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('leads', 'primary_contact_id'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('comments', 'company_id'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('interactions', 'contact_id'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
