<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

it('staging demo seederi staging veya prod ortamında hardcoded parola ile çalışmayı kesinlikle reddeder', function (): void {
    config(['app.env' => 'staging']);

    expect(fn () => (new DemoDataSeeder)->setContainer(app())->run())
        ->toThrow(RuntimeException::class);

    config(['app.env' => 'production']);

    expect(fn () => (new DemoDataSeeder)->setContainer(app())->run())
        ->toThrow(RuntimeException::class);
});

it('staging provision komutu eksik veya zayıf parolada işlemi derhal durdurur ve kullanıcı oluşturmaz', function (): void {
    /** @var TestCase $this */
    // Weak password case
    putenv('STAGING_MARKETING_EMAIL=pazarlama@pilot.bizlife.invalid');
    putenv('STAGING_MARKETING_PASSWORD=zayif');
    putenv('STAGING_PM_EMAIL=pm@pilot.bizlife.invalid');
    putenv('STAGING_PM_PASSWORD=GucluParolaPM123!');
    putenv('STAGING_COMPANY_AUTHORITY_EMAIL=yetkili@pilot.bizlife.invalid');
    putenv('STAGING_COMPANY_AUTHORITY_PASSWORD=GucluParolaYetkili123!');
    putenv('STAGING_SYSTEM_ADMIN_EMAIL=admin@pilot.bizlife.invalid');
    putenv('STAGING_SYSTEM_ADMIN_PASSWORD=GucluParolaAdmin123!');

    $this->artisan('system:provision-staging-demo')->assertFailed();

    expect(User::query()->where('email', 'pazarlama@pilot.bizlife.invalid')->exists())->toBeFalse();
});

it('staging provision komutu 4 ayrı rolü ve doğru kapsamları başarıyla kurar', function (): void {
    /** @var TestCase $this */
    $envVars = [
        'STAGING_MARKETING_EMAIL' => 'pazarlama@pilot.bizlife.invalid',
        'STAGING_MARKETING_PASSWORD' => 'GucluParolaPzr123!',
        'STAGING_PM_EMAIL' => 'pm@pilot.bizlife.invalid',
        'STAGING_PM_PASSWORD' => 'GucluParolaPM123!',
        'STAGING_COMPANY_AUTHORITY_EMAIL' => 'yetkili@pilot.bizlife.invalid',
        'STAGING_COMPANY_AUTHORITY_PASSWORD' => 'GucluParolaYetkili123!',
        'STAGING_SYSTEM_ADMIN_EMAIL' => 'admin@pilot.bizlife.invalid',
        'STAGING_SYSTEM_ADMIN_PASSWORD' => 'GucluParolaAdmin123!',
    ];

    foreach ($envVars as $k => $v) {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    $this->artisan('system:provision-staging-demo')->assertSuccessful();

    $marketing = User::query()->where('email', 'pazarlama@pilot.bizlife.invalid')->first();
    $pm = User::query()->where('email', 'pm@pilot.bizlife.invalid')->first();
    $authority = User::query()->where('email', 'yetkili@pilot.bizlife.invalid')->first();
    $admin = User::query()->where('email', 'admin@pilot.bizlife.invalid')->first();

    expect($marketing)->not->toBeNull()
        ->and($marketing->hasRole('Pazarlama'))->toBeTrue()
        ->and($marketing->data_scope)->toBe('own')
        ->and($pm)->not->toBeNull()
        ->and($pm->hasRole('Proje Yöneticisi'))->toBeTrue()
        ->and($pm->data_scope)->toBe('team')
        ->and($authority)->not->toBeNull()
        ->and($authority->hasRole('Şirket Yetkilisi'))->toBeTrue()
        ->and($authority->data_scope)->toBe('all')
        ->and($admin)->not->toBeNull()
        ->and($admin->hasRole('Sistem Yöneticisi'))->toBeTrue()
        ->and($admin->data_scope)->toBe('none')
        ->and($admin->hasDirectPermission('page.access_management'))->toBeTrue();

    // Takım kontrolü
    $team = Team::query()->where('name', 'Kurgusal Pilot Takımı')->first();
    expect($team)->not->toBeNull()
        ->and($team->manager_id)->toBe($pm->id)
        ->and($team->members()->pluck('users.id')->all())->toContain($pm->id, $marketing->id);
});

it('staging ortamında X-Robots-Tag başlığı eklenir', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    $response = $this->get('/operasyon/login');
    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('readiness healthcheck endpointi sistem çalışırken 200 döner', function (): void {
    /** @var TestCase $this */
    $response = $this->getJson('/health');
    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'services' => [
                'database' => 'ok',
                'redis' => 'ok',
                'storage' => 'ok',
            ],
        ]);
});
