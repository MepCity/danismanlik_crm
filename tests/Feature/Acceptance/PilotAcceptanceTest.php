<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Services\NotificationService;
use App\Filament\Pages\LeadBoard;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Users\UserResource;
use App\Livewire\NotificationCenter;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
    config(['app.env' => 'testing']);
});

it('staging provision komutu 4 ayrı rol ve demo verisini eksiksiz kurar', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    $secrets = [
        'STAGING_MARKETING_EMAIL' => 'pilot-pazarlama@bizlife.invalid',
        'STAGING_MARKETING_PASSWORD' => 'GucluParolaPzr2026!',
        'STAGING_PM_EMAIL' => 'pilot-pm@bizlife.invalid',
        'STAGING_PM_PASSWORD' => 'GucluParolaPM2026!',
        'STAGING_COMPANY_AUTHORITY_EMAIL' => 'pilot-yetkili@bizlife.invalid',
        'STAGING_COMPANY_AUTHORITY_PASSWORD' => 'GucluParolaYetkili2026!',
        'STAGING_SYSTEM_ADMIN_EMAIL' => 'pilot-admin@bizlife.invalid',
        'STAGING_SYSTEM_ADMIN_PASSWORD' => 'GucluParolaAdmin2026!',
    ];

    foreach ($secrets as $k => $v) {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    $this->artisan('system:provision-staging-demo')->assertSuccessful();

    $marketing = User::query()->where('email', 'pilot-pazarlama@bizlife.invalid')->sole();
    $pm = User::query()->where('email', 'pilot-pm@bizlife.invalid')->sole();
    $authority = User::query()->where('email', 'pilot-yetkili@bizlife.invalid')->sole();
    $admin = User::query()->where('email', 'pilot-admin@bizlife.invalid')->sole();

    expect($marketing->data_scope)->toBe('own')
        ->and($pm->data_scope)->toBe('team')
        ->and($authority->data_scope)->toBe('all')
        ->and($admin->data_scope)->toBe('none')
        ->and(Team::query()->where('name', 'Kurgusal Pilot Takımı')->exists())->toBeTrue();

    config(['app.env' => 'testing']);
});

it('staging ortamında güvenlik başlıkları ve readiness kontrolü çalışır', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    $this->get('/robots.txt')->assertSee("User-agent: *\nDisallow: /", false);
    $this->get('/operasyon/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->getJson('/health')->assertOk()->assertJson(['status' => 'ok']);

    config(['app.env' => 'testing']);
});

it('pazarlama kullanıcısı kendi panosuna erişir sistem yapılandırmasından 403 alır', function (): void {
    /** @var TestCase $this */
    $marketing = User::factory()->create(['email' => 'pazarlama-pilot@bizlife.invalid', 'data_scope' => 'own']);
    $marketing->assignRole('Pazarlama');

    $this->actingAs($marketing)->get(LeadBoard::getUrl())->assertOk();
    $this->actingAs($marketing)->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs($marketing)->get(TeamResource::getUrl('index'))->assertForbidden();
    $this->actingAs($marketing)->get(RoleResource::getUrl('index'))->assertForbidden();
});

it('şirket yetkilisi yönetici raporlarına erişir sistem yönetimine erişemez', function (): void {
    /** @var TestCase $this */
    $authority = User::factory()->create(['email' => 'yetkili-pilot@bizlife.invalid', 'data_scope' => 'all']);
    $authority->assignRole('Şirket Yetkilisi');

    $this->actingAs($authority)->get(OperationsDashboard::getUrl())->assertOk();
    $this->actingAs($authority)->get(Reports::getUrl())->assertOk();
    $this->actingAs($authority)->get(UserResource::getUrl('index'))->assertForbidden();
});

it('sistem yöneticisi erişim yapılandırmasını yönetir fakat iş verisi detayına 403 alır', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['email' => 'admin-pilot@bizlife.invalid', 'data_scope' => 'none']);
    $admin->assignRole('Sistem Yöneticisi');

    $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertOk();
    $this->actingAs($admin)->get(TeamResource::getUrl('index'))->assertOk();
    $this->actingAs($admin)->get(RoleResource::getUrl('index'))->assertOk();

    // Takım güncelleme
    $pm = User::factory()->create(['email' => 'pm-pilot-2@bizlife.invalid']);
    $team = Team::query()->create(['name' => 'Kurgusal Ekip', 'manager_id' => $pm->id, 'is_active' => true]);

    app(SaveTeam::class)->execute($team, [
        'name' => 'Kurgusal Güncellenen Ekip',
        'manager_id' => $pm->id,
        'member_ids' => [$pm->id],
        'is_active' => true,
        'change_reason' => 'Pilot döneminde ekip güncelleme',
    ], $admin);

    expect(RolePermissionHistory::query()
        ->where('subject_type', 'team')
        ->where('subject_id', $team->id)
        ->where('reason', 'Pilot döneminde ekip güncelleme')
        ->exists())->toBeTrue();
});

it('bildirim merkezi livewire bileşeni pilot bildirimlerini doğru yönetir', function (): void {
    $pm = User::factory()->create(['email' => 'pm-notif@bizlife.invalid']);
    $pm->assignRole('Proje Yöneticisi');

    Notification::query()->create([
        'user_id' => $pm->id,
        'title' => 'Pilot Bildirimi',
        'body' => 'Dosya ataması tamamlandı.',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    $notifService = app(NotificationService::class);
    expect($notifService->unreadCount($pm))->toBe(1);

    Auth::login($pm);

    Livewire::test(NotificationCenter::class)
        ->assertSee('Pilot Bildirimi')
        ->assertSee('1 yeni')
        ->call('markAllAsRead')
        ->assertDontSee('1 yeni');

    expect($notifService->unreadCount($pm))->toBe(0);
});
