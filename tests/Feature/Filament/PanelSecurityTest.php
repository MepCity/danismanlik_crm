<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Status;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

function panelUser(string $role, string $slug): User
{
    $user = User::factory()->create([
        'email' => $slug.'@example.invalid',
        'app_authentication_secret' => 'KURGUSAL-TOTP-SECRET',
        'app_authentication_recovery_codes' => ['KURGUSAL-KURTARMA'],
    ]);
    $user->assignRole($role);

    return $user;
}

function panelCompany(User $owner, string $slug): Company
{
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal '.$slug.' İşletmesi',
        'city' => '06',
    ]);
    $status = Status::query()->where('type', 'lead')->firstOrFail();
    Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status_id' => $status->id,
    ]);

    return $company;
}

/** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
function panelGet(User $user, string $uri): TestResponse
{
    Auth::login($user);
    $response = app(Kernel::class)->handle(Request::create($uri, 'GET'));

    return TestResponse::fromBaseResponse($response);
}

it('pasif kullanıcının doğru parolayla panel oturumu açmasını reddeder', function (): void {
    $user = User::factory()->create([
        'email' => 'pasif-kullanici@example.invalid',
        'password' => 'KurgusalParola123!',
        'is_active' => false,
    ]);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'KurgusalParola123!'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth()->check())->toBeFalse();
});

it('panel liste sorgusunu pazarlama kullanıcısının kapsamına indirger', function (): void {
    $marketing = panelUser('Pazarlama', 'panel-pazarlama');
    $other = panelUser('Pazarlama', 'panel-diger');
    $own = panelCompany($marketing, 'PANEL-OWN');
    $foreign = panelCompany($other, 'PANEL-FOREIGN');

    Auth::login($marketing);

    expect(CompanyResource::getEloquentQuery()->orderBy('id')->pluck('id')->all())
        ->toBe([$own->id]);

    panelGet($marketing, CompanyResource::getUrl('index'))
        ->assertOk()
        ->assertSee($own->legal_name)
        ->assertDontSee($foreign->legal_name);
});

it('her rolün panel liste sorgusunu varsayılan kapsamıyla çalıştırır', function (string $role, int $expectedCount): void {
    $user = panelUser($role, 'rol-'.str($role)->slug());
    panelCompany($user, 'ROL-OWN-'.str($role)->slug());
    panelCompany(panelUser('Pazarlama', 'rol-yabanci-'.str($role)->slug()), 'ROL-FOREIGN-'.str($role)->slug());

    Auth::login($user);

    expect(CompanyResource::getEloquentQuery()->count())->toBe($expectedCount);
})->with([
    'Pazarlama' => ['Pazarlama', 1],
    'Proje Yöneticisi' => ['Proje Yöneticisi', 1],
    'Şirket Yetkilisi' => ['Şirket Yetkilisi', 2],
    'Sistem Yöneticisi' => ['Sistem Yöneticisi', 0],
]);

it('kapsam dışı detay url erişimini 403 ile reddeder', function (): void {
    $marketing = panelUser('Pazarlama', 'detay-pazarlama');
    $foreign = panelCompany(panelUser('Pazarlama', 'detay-diger'), 'DETAY-YABANCI');

    panelGet($marketing, CompanyResource::getUrl('view', ['record' => $foreign]))
        ->assertForbidden();
});

it('yetkisiz kaynağı menüden gizler', function (): void {
    $admin = panelUser('Sistem Yöneticisi', 'menu-sistem');

    Auth::login($admin);

    expect(CompanyResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CompanyResource::canAccess())->toBeFalse();

    panelGet($admin, '/operasyon')->assertDontSee(__('panel.resources.companies.navigation'));
});

it('zorunlu rolleri totp kurulumu olmadan panele almaz', function (string $role): void {
    $user = User::factory()->create(['email' => str($role)->slug().'@example.invalid']);
    $user->assignRole($role);

    panelGet($user, '/operasyon')
        ->assertRedirect(route('filament.operations.auth.multi-factor-authentication.set-up-required'));
})->with(['Şirket Yetkilisi', 'Sistem Yöneticisi']);

it('totp sırlarını ve kurtarma kodlarını denetim jsonuna yazmaz', function (): void {
    $user = panelUser('Şirket Yetkilisi', 'audit-mfa');

    $user->saveAppAuthenticationSecret('DENETIMDE-GORUNMEMELI');
    $user->saveAppAuthenticationRecoveryCodes(['KOD-GORUNMEMELI']);

    $payload = DB::table('audit_log')
        ->where('table_name', 'users')
        ->where('row_id', $user->id)
        ->orderByDesc('created_at')
        ->limit(2)
        ->get(['old_data', 'new_data'])
        ->toJson();

    expect(str_contains($payload, 'app_authentication_secret'))->toBeFalse()
        ->and(str_contains($payload, 'app_authentication_recovery_codes'))->toBeFalse()
        ->and(str_contains($payload, 'DENETIMDE-GORUNMEMELI'))->toBeFalse()
        ->and(str_contains($payload, 'KOD-GORUNMEMELI'))->toBeFalse();
});
