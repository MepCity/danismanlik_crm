<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('e-posta şablonu sayfa iznini geri alınabilir biçimde pasifleştirir', function (): void {
    $schema = 'email_page_'.Str::lower(Str::random(12));
    $connection = 'email_page_contract';
    $configuration = config('database.connections.testing');
    expect($configuration)->toBeArray();

    DB::statement("CREATE SCHEMA {$schema}");
    config()->set("database.connections.{$connection}", [...$configuration, 'search_path' => $schema]);

    try {
        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(DB::connection($connection)->table('permissions')->where('name', 'page.email_templates')->value('is_active'))->toBeFalse();

        $rollbackSteps = DB::connection($connection)->table('migrations')
            ->where('migration', '>=', '2026_08_27_090000_retire_email_template_page_permission')
            ->count();

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => $rollbackSteps,
        ]))->toBe(0)
            ->and(DB::connection($connection)->table('permissions')->where('name', 'page.email_templates')->value('is_active'))->toBeTrue();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(DB::connection($connection)->table('permissions')->where('name', 'page.email_templates')->value('is_active'))->toBeFalse();
    } finally {
        DB::purge($connection);
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
});
