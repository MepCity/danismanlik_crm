<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Document\Models\DealDocument;
use App\Livewire\NotificationCenter;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    Storage::fake('s3');
    config()->set('filesystems.default', 's3');
});

it('staging demo seederi staging veya prod ortamında hardcoded parola ile çalışmayı kesinlikle reddeder', function (): void {
    config(['app.env' => 'staging']);

    expect(fn () => (new DemoDataSeeder)->setContainer(app())->run())
        ->toThrow(RuntimeException::class);

    config(['app.env' => 'production']);

    expect(fn () => (new DemoDataSeeder)->setContainer(app())->run())
        ->toThrow(RuntimeException::class);

    config(['app.env' => 'testing']);
});

it('staging provision komutu production, local ve testing ortamlarında çalışmayı veri yazmadan reddeder', function (): void {
    /** @var TestCase $this */
    // 1. Production
    config(['app.env' => 'production']);
    $this->artisan('system:provision-staging-demo')->assertFailed();

    // 2. Local
    config(['app.env' => 'local']);
    $this->artisan('system:provision-staging-demo')->assertFailed();

    // 3. Testing (no bypass option exists)
    config(['app.env' => 'testing']);
    $this->artisan('system:provision-staging-demo')->assertFailed();

    expect(User::query()->where('email', 'pazarlama@pilot.bizlife.invalid')->exists())->toBeFalse()
        ->and(Company::query()->where('tax_number', '1234567890')->exists())->toBeFalse();
});

it('staging provision komutu eksik veya zayıf parolada işlemi derhal durdurur ve kullanıcı oluşturmaz', function (string $weakPassword): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    putenv('STAGING_MARKETING_EMAIL=pazarlama@pilot.bizlife.invalid');
    putenv("STAGING_MARKETING_PASSWORD={$weakPassword}");
    putenv('STAGING_PM_EMAIL=pm@pilot.bizlife.invalid');
    putenv('STAGING_PM_PASSWORD=GucluParolaPM123!');
    putenv('STAGING_COMPANY_AUTHORITY_EMAIL=yetkili@pilot.bizlife.invalid');
    putenv('STAGING_COMPANY_AUTHORITY_PASSWORD=GucluParolaYetkili123!');
    putenv('STAGING_SYSTEM_ADMIN_EMAIL=admin@pilot.bizlife.invalid');
    putenv('STAGING_SYSTEM_ADMIN_PASSWORD=GucluParolaAdmin123!');

    $this->artisan('system:provision-staging-demo')->assertFailed();

    expect(User::query()->where('email', 'pazarlama@pilot.bizlife.invalid')->exists())->toBeFalse();

    config(['app.env' => 'testing']);
})->with([
    'kısa' => 'Kisa1!',
    'büyük harf yok' => 'kucukharf1234!',
    'küçük harf yok' => 'BUYUKHARF1234!',
    'rakam yok' => 'RakamYokParola!',
    'sembol yok' => 'SembolYok123456',
]);

it('staging provision komutu 4 ayrı rolü, takımı, firmayı, fırsatı, dosyayı ve evrak kontrol listesini başarıyla kurar', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

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

    // Firma ve Kontak kontrolü
    $company = Company::query()->where('tax_number', '1234567890')->first();
    expect($company)->not->toBeNull()
        ->and($company->owner_user_id)->toBe($marketing->id)
        ->and($company->contacts()->count())->toBe(1);

    // Fırsat (Lead) ve Görüşme (Interaction) kontrolü
    $lead = Lead::query()->where('company_id', $company->id)->first();
    expect($lead)->not->toBeNull()
        ->and($lead->owner_user_id)->toBe($marketing->id)
        ->and($lead->converted_deal_id)->not->toBeNull()
        ->and(Interaction::query()->where('lead_id', $lead->id)->count())->toBe(1);

    // Dosya (Deal) ve PM atama kontrolü
    $deal = Deal::query()->where('company_id', $company->id)->first();
    expect($deal)->not->toBeNull()
        ->and($deal->pm_user_id)->toBe($pm->id)
        ->and($deal->requested_amount)->toBe('6000000.00')
        ->and($deal->status)->not->toBeNull()
        ->and($deal->status->code)->toBe('pm_assigned')
        ->and(DealDocument::query()->where('deal_id', $deal->id)->count())->toBeGreaterThan(0)
        ->and(StatusHistory::query()->where('deal_id', $deal->id)->count())->toBeGreaterThan(0)
        ->and(Activity::query()->where('deal_id', $deal->id)->count())->toBeGreaterThan(0)
        ->and(Notification::query()->where('user_id', $pm->id)->where('deal_id', $deal->id)->count())->toBeGreaterThan(0);

    config(['app.env' => 'testing']);
});

it('staging provision komutu ikinci çalıştırmada idempotence korur ve kayıtları çoğaltmaz', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

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

    // İlk çalıştırma
    $this->artisan('system:provision-staging-demo')->assertSuccessful();

    $userCount = User::query()->count();
    $teamCount = Team::query()->count();
    $companyCount = Company::query()->count();
    $leadCount = Lead::query()->count();
    $dealCount = Deal::query()->count();
    $docCount = DealDocument::query()->count();
    $historyCount = StatusHistory::query()->count();
    $activityCount = Activity::query()->count();

    // İkinci çalıştırma
    $this->artisan('system:provision-staging-demo')->assertSuccessful();

    expect(User::query()->count())->toBe($userCount)
        ->and(Team::query()->count())->toBe($teamCount)
        ->and(Company::query()->count())->toBe($companyCount)
        ->and(Lead::query()->count())->toBe($leadCount)
        ->and(Deal::query()->count())->toBe($dealCount)
        ->and(DealDocument::query()->count())->toBe($docCount)
        ->and(StatusHistory::query()->count())->toBe($historyCount)
        ->and(Activity::query()->count())->toBe($activityCount);

    config(['app.env' => 'testing']);
});

it('staging ortamında panel, health ve robots.txt üzerinde X-Robots-Tag başlığı doğru üretilir', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    // Panel
    $panelResp = $this->get('/operasyon/login');
    $panelResp->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    // Health
    $healthResp = $this->getJson('/health');
    $healthResp->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    // Robots.txt
    $robotsResp = $this->get('/robots.txt');
    $robotsResp->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertSee("User-agent: *\nDisallow: /", false);

    config(['app.env' => 'testing']);
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

it('readiness healthcheck bozuk depolama durumunda 503 döner ve sır sızdırmaz', function (): void {
    /** @var TestCase $this */
    Storage::shouldReceive('disk')->andThrow(new RuntimeException('S3 AWS credentials invalid or bucket missing: secret_access_key=xyz'));

    $response = $this->getJson('/health');
    $response->assertStatus(503)
        ->assertExactJson(['status' => 'unhealthy'])
        ->assertDontSee('secret_access_key')
        ->assertDontSee('S3 AWS credentials');
});

it('staging uyarı şeridi ve bildirim merkezi semantik tasarım token sınıflarını kullanır ve doğrudan renk sınıfı içermez', function (): void {
    $bannerView = (string) view('filament.components.staging-banner')->render();
    expect($bannerView)->toContain('staging-warning-banner')
        ->and($bannerView)->not->toContain('bg-amber-')
        ->and($bannerView)->not->toContain('text-amber-');

    $notifHtml = Livewire::test(NotificationCenter::class)->html();

    expect($notifHtml)->toContain('notification-center')
        ->and($notifHtml)->toContain('notification-center-trigger')
        ->and($notifHtml)->not->toContain('text-zinc-')
        ->and($notifHtml)->not->toContain('bg-zinc-')
        ->and($notifHtml)->not->toContain('text-emerald-')
        ->and($notifHtml)->not->toContain('bg-emerald-')
        ->and($notifHtml)->not->toContain('bg-white');
});
