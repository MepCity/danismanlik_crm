<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('firma rehberi alanlarını PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $schema = 'company_directory_'.Str::lower(Str::random(12));
    $connection = 'company_directory_contract';
    $configuration = config('database.connections.testing');
    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [...$configuration, 'search_path' => $schema]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('companies', 'owner_user_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('companies', 'industry'))->toBeTrue();

        expect(fn () => DB::connection($connection)->table('companies')->insert([
            'legal_name' => 'Kurgusal Geçersiz Sektör AŞ',
            'industry' => 'serbest-metin',
            'city' => null,
        ]))->toThrow(QueryException::class);

        $rollbackSteps = DB::connection($connection)->table('migrations')
            ->where('migration', '>=', '2026_08_21_090000_add_company_directory_fields')
            ->count();

        expect(Artisan::call('migrate:rollback', ['--database' => $connection, '--force' => true, '--step' => $rollbackSteps]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('companies', 'owner_user_id'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('companies', 'industry'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('companies', 'industry'))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
