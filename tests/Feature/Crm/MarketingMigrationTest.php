<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('pazarlama operasyon şemasını PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $connection = 'testing';

    try {
        expect(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeTrue();

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => 1,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeTrue();
    } finally {
        Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
    }
});
