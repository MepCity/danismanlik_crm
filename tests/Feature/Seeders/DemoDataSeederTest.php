<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Deal\Models\Deal;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('production ortamında yazmadan önce demo seederı reddeder', function (): void {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $seeder = (new DemoDataSeeder)->setContainer(app());

        expect(fn () => $seeder->run())
            ->toThrow(RuntimeException::class, 'Demo verileri production ortamında yüklenemez.');
        expect(User::query()->count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
});

it('tamamen kurgusal demo grafiğini ve sabit hesapları kurar', function (): void {
    (new DemoDataSeeder)->setContainer(app())->run();

    $marketing = User::query()->where('email', 'pazarlama@demo.invalid')->firstOrFail();

    expect(User::query()->where('email', 'like', '%@demo.invalid')->count())->toBe(5)
        ->and($marketing->hasRole('Pazarlama'))->toBeTrue()
        ->and(Hash::check(DemoDataSeeder::PASSWORD, $marketing->password))->toBeTrue()
        ->and(Team::query()->count())->toBe(2)
        ->and(Deal::query()->count())->toBe(3)
        ->and(Deal::query()->withCount('documents')->get()->pluck('documents_count')->all())
        ->each->toBe(7);
});
