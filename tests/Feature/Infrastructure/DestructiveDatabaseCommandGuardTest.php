<?php

declare(strict_types=1);

use App\Support\Database\Console\SafeFreshCommand;
use App\Support\Database\Console\SafeRefreshCommand;
use App\Support\Database\Console\SafeWipeCommand;
use App\Support\Database\DestructiveDatabaseCommandGuard;
use Illuminate\Support\Facades\Artisan;

it('yıkıcı artisan komutlarını korumalı giriş noktalarıyla kaydeder', function (): void {
    $commands = Artisan::all();

    expect($commands['migrate:fresh'])->toBeInstanceOf(SafeFreshCommand::class)
        ->and($commands['migrate:refresh'])->toBeInstanceOf(SafeRefreshCommand::class)
        ->and($commands['db:wipe'])->toBeInstanceOf(SafeWipeCommand::class);
});

it('testing ortamında yanlış hedefe yönelen yıkıcı komutları reddeder', function (string $command): void {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing.database', 'tesvik_crm');

    expect(fn () => app(DestructiveDatabaseCommandGuard::class)
        ->ensureTestingDatabaseIsSafe($command))
        ->toThrow(
            RuntimeException::class,
            'APP_ENV=testing ama hedef veritabanı tesvik_crm — reddedildi.',
        );
})->with([
    'migrate:fresh',
    'migrate:refresh',
    'db:wipe',
]);

it('testing ortamında yalnız sabit test veritabanına izin verir', function (): void {
    config()->set('database.default', 'testing');
    config()->set('database.connections.testing.database', 'tesvik_crm_test');

    app(DestructiveDatabaseCommandGuard::class)
        ->ensureTestingDatabaseIsSafe('migrate:fresh');

    expect(true)->toBeTrue();
});

it('database seçeneğiyle verilen bağlantının gerçek hedefini denetler', function (): void {
    config()->set('database.connections.pgsql.database', 'tesvik_crm');

    expect(fn () => app(DestructiveDatabaseCommandGuard::class)
        ->ensureTestingDatabaseIsSafe('db:wipe', 'pgsql'))
        ->toThrow(
            RuntimeException::class,
            'APP_ENV=testing ama hedef veritabanı tesvik_crm — reddedildi.',
        );
});
