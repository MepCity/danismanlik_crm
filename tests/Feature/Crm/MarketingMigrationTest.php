<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('pazarlama operasyon şemasını PostgreSQL üzerinde geri alıp yeniden kurar', function (): void {
    $connection = 'testing';

    try {
        expect(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'do_not_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_sms'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_email'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'decision_role'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('communication_consents', 'disclosure_method'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'duration_minutes'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'direction'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'purpose'))->toBeTrue();

        $rollbackSteps = DB::connection($connection)->table('migrations')
            ->where('migration', '>=', '2026_08_16_090000_add_marketing_operations_fields')
            ->count();

        expect(Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--force' => true,
            '--step' => $rollbackSteps,
        ]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'direction'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'purpose'))->toBeFalse();

        expect(Artisan::call('migrate', ['--database' => $connection, '--force' => true]))->toBe(0)
            ->and(Schema::connection($connection)->hasColumn('contacts', 'data_source'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'do_not_call'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_sms'))->toBeFalse()
            ->and(Schema::connection($connection)->hasColumn('contacts', 'consent_email'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('statuses', 'required_fields'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('leads', 'converted_deal_id'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'direction'))->toBeTrue()
            ->and(Schema::connection($connection)->hasColumn('interactions', 'purpose'))->toBeTrue();
    } finally {
        Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
    }
});
