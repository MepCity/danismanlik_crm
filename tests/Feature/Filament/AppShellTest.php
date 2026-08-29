<?php

declare(strict_types=1);

use App\Filament\Pages\DealBoard;
use App\Filament\Pages\LeadBoard;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\PendingAssignments;
use App\Filament\Pages\Reports;
use App\Filament\Pages\TodayCalls;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Filament\Resources\Statuses\StatusResource;
use App\Filament\Resources\Transitions\TransitionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

function shellUser(string $role, string $slug): User
{
    $user = User::factory()->create(['email' => $slug.'@example.invalid']);
    $user->assignRole($role);

    return $user;
}

/** @return TestResponse<Response> */
function shellGet(User $user, string $uri): TestResponse
{
    Auth::login($user);

    return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, 'GET')));
}

it('yetkili kullanıcı için altı ana ray öğesini render eder', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-six-items');

    $response = shellGet($officer, OperationsDashboard::getUrl());

    $response->assertOk();

    foreach (['dashboard', 'marketing', 'companies', 'files', 'reports', 'other'] as $key) {
        $response->assertSee('data-rail-key="'.$key.'"', false);
    }

    foreach (['Ana panel', 'Pazarlama', 'Firmalar', 'Dosyalar', 'Raporlar', 'Diğer'] as $label) {
        $response->assertSee($label, false);
    }

    $response->assertSee('href="'.OperationsDashboard::getUrl().'"', false);
    $response->assertSee('href="'.Reports::getUrl().'"', false);
});

it('iş akışları sayfasında diğer öğesini seçili ve aria-current ile işaretler', function (): void {
    $admin = shellUser('Sistem Yöneticisi', 'shell-workflows-selected');

    $response = shellGet($admin, ServiceWorkflowResource::getUrl());

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('data-rail-key="other"')
        ->and($content)->toMatch('/data-rail-key="other".*?aria-current="true"/s')
        ->and($content)->toMatch('/aria-current="page"[^>]*>\s*İş Akışları/s');
});

it('pazarlama flyoutu bugün aranacaklar ve takip panosu rotalarını taşır', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-marketing-routes');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toContain('href="'.TodayCalls::getUrl().'"')
        ->and($content)->toContain('href="'.LeadBoard::getUrl().'"');
});

it('firmalar flyoutu firmalar ve müşteriler rotalarını taşır', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-companies-routes');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toContain('href="'.CompanyResource::getUrl().'"')
        ->and($content)->toContain('href="'.CustomerResource::getUrl().'"');
});

it('dosyalar flyoutu atama bekleyen işler ve dosya panosu rotalarını taşır', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-files-routes');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toContain('href="'.PendingAssignments::getUrl().'"')
        ->and($content)->toContain('href="'.DealBoard::getUrl().'"');
});

it('diğer flyoutu yapılandırma rotalarının tamamını taşır', function (): void {
    $admin = shellUser('Sistem Yöneticisi', 'shell-other-routes');

    $response = shellGet($admin, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toContain('href="'.ServiceWorkflowResource::getUrl().'"')
        ->and($content)->toContain('href="'.ProgramResource::getUrl().'"')
        ->and($content)->toContain('href="'.StatusResource::getUrl().'"')
        ->and($content)->toContain('href="'.TransitionResource::getUrl().'"')
        ->and($content)->toContain('href="'.UserResource::getUrl().'"');
});

it('yetkisiz kullanıcıya diğer öğesini ve erişemediği rotaları göstermez', function (): void {
    $marketer = shellUser('Pazarlama', 'shell-unauthorized-hidden');

    $response = shellGet($marketer, OperationsDashboard::getUrl());
    $response->assertOk();
    $content = $response->getContent();

    expect($content)->not->toContain('data-rail-key="other"')
        ->and($content)->not->toContain('href="'.ServiceWorkflowResource::getUrl().'"')
        ->and($content)->not->toContain('href="'.UserResource::getUrl().'"')
        ->and($content)->not->toContain('href="'.PendingAssignments::getUrl().'"')
        ->and($content)->toContain('data-rail-key="dashboard"')
        ->and($content)->toContain('data-rail-key="marketing"')
        ->and($content)->toContain('data-rail-key="companies"')
        ->and($content)->toContain('data-rail-key="files"')
        ->and($content)->toContain('data-rail-key="reports"');
});

it('gizlenen linki bir sunum kolaylığı sayar ve doğrudan yetkisiz url için 403 döner', function (): void {
    $marketer = shellUser('Pazarlama', 'shell-direct-403');

    $response = shellGet($marketer, ServiceWorkflowResource::getUrl());

    $response->assertForbidden();
});

it('kapsam dışı takvim yazışmalar ve genel arama öğelerini içermez', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-out-of-scope');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    foreach (['Takvim', 'Yazışmalar'] as $outOfScopeLabel) {
        expect($content)->not->toContain('crm-rail__label">'.$outOfScopeLabel);
    }
});

it('üst çubukta marka ve kullanıcı menüsünü korur', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-topbar');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toContain('crm-brand')
        ->and($content)->toContain(__('panel.brand'))
        ->and($content)->toContain('fi-user-menu-trigger');
});

it('ana rayın üst bölümünde bizlife marka işaretini render eder', function (): void {
    $officer = shellUser('Şirket Yetkilisi', 'shell-rail-brand');

    $response = shellGet($officer, OperationsDashboard::getUrl());
    $content = $response->getContent();

    expect($content)->toMatch('/fi-sidebar-header.*?crm-brand__mark/s')
        ->and($content)->toContain('crm-brand__mark');

    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));

    expect($theme)->toContain('min-height: var(--crm-shell-rail-brand-height);')
        ->and($theme)->toContain('width: var(--crm-shell-rail-brand-mark);')
        ->and($tokens)->toContain('--crm-shell-rail-brand-height: 96px;');
});

it('seçili gezinme öğesini renkten bağımsız dairesel yüzeyle kodlar', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->toContain('--crm-shell-rail-selected-size: 66px;')
        ->and($tokens)->toContain('--crm-shell-rail-selected-radius: 50%;')
        ->and($tokens)->toContain('--crm-shell-rail-item-height: 65.5px;')
        ->and($theme)->toContain('border-radius: var(--crm-shell-rail-selected-radius);')
        ->and($theme)->toContain('height: var(--crm-shell-rail-item-height);')
        ->and($theme)->toContain('.crm-rail__control--selected');
});

it('içerik tipografisini 12/18 gövde ve 18/21.6 başlık sözleşmesinde tutar', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->toContain('--crm-shell-body-size: 12px;')
        ->and($tokens)->toContain('--crm-shell-body-line: 18px;')
        ->and($tokens)->toContain('--crm-shell-title-size: 18px;')
        ->and($tokens)->toContain('--crm-shell-title-line: 21.6px;')
        ->and($theme)->toContain('font-size: var(--crm-shell-body-size);')
        ->and($theme)->toContain('line-height: var(--crm-shell-body-line);')
        ->and($theme)->toContain('font-size: var(--crm-shell-title-size);')
        ->and($theme)->toContain('line-height: var(--crm-shell-title-line);');
});

it('dikey içerik başlangıcını sayfaya özel hack yerine ortak sözleşmede kurar', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->toContain('--crm-shell-content-block-start: 25px;')
        ->and($theme)->toContain('padding-block: var(--crm-shell-content-block-start)')
        ->and($theme)->not->toContain('.fi-resource-service-workflows .fi-page-header-main-ctn');
});

it('rail navigasyon metinlerini lang/tr dosyasından okur', function (): void {
    $railNavigation = file_get_contents(app_path('Filament/Navigation/RailNavigation.php'));
    $rail = file_get_contents(resource_path('views/filament/components/navigation/rail.blade.php'));
    $lang = file_get_contents(lang_path('tr/panel.php'));

    expect($railNavigation)->not->toBeFalse()
        ->and($rail)->not->toBeFalse()
        ->and($lang)->not->toBeFalse()
        ->and($railNavigation)->toContain("__('panel.navigation.rail.marketing')")
        ->and($railNavigation)->toContain("__('panel.navigation.rail.companies')")
        ->and($railNavigation)->toContain("__('panel.navigation.rail.files')")
        ->and($railNavigation)->toContain("__('panel.navigation.rail.other')")
        ->and($lang)->toContain("'marketing' => 'Pazarlama'")
        ->and($lang)->toContain("'companies' => 'Firmalar'")
        ->and($lang)->toContain("'files' => 'Dosyalar'")
        ->and($lang)->toContain("'other' => 'Diğer'");
});

it('kabuk geometrisini tokenlarla 100px ray ve 60px üst çubuğa sabitler', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->not->toBeFalse()
        ->and($theme)->not->toBeFalse()
        ->and($tokens)->toContain('--crm-shell-rail-width: 100px;')
        ->and($tokens)->toContain('--crm-shell-topbar-height: 60px;')
        ->and($tokens)->toContain('--crm-shell-content-inset: 20px;')
        ->and($tokens)->toContain('--crm-shell-content-max-width: 1030px;')
        ->and($tokens)->toContain('--crm-shell-transition: 150ms ease-in-out;')
        ->and($theme)->toContain('.fi-sidebar {')
        ->and($theme)->toContain('width: var(--crm-shell-rail-width);')
        ->and($theme)->toContain('min-height: var(--crm-shell-topbar-height);');
});
