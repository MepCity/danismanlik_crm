<?php

declare(strict_types=1);

use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\BreakGlassGrants\BreakGlassGrantResource;
use App\Filament\Resources\DocTemplates\DocTemplateResource;
use App\Filament\Resources\DocTemplates\Pages\CreateDocTemplate;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\ProgramVersions\ProgramVersionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Filament\Resources\Statuses\StatusResource;
use App\Filament\Resources\Transitions\TransitionResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\StatusBadge;
use App\Models\User;
use App\Support\Conditions\ConditionDefinition;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

function wp15User(string $role, string $slug): User
{
    $user = User::factory()->create([
        'email' => $slug.'@example.invalid',
        'app_authentication_secret' => 'KURGUSAL-WP15-TOTP',
        'app_authentication_recovery_codes' => ['KURGUSAL-WP15-KOD'],
    ]);
    $user->assignRole($role);

    return $user;
}

/** @return TestResponse<Response> */
function wp15Get(User $user, string $uri): TestResponse
{
    Auth::login($user);

    return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, 'GET')));
}

it('koşul önizlemesini yöneticiye Türkçe cümle olarak verir', function (): void {
    expect(ConditionDefinition::preview(['all' => [[
        'field' => 'company.city',
        'op' => 'in',
        'value' => ['01', '02', '31'],
    ]]]))->toBe('Firma ili 01, 02, 31 illerinden biriyse zorunlu');
});

it('bilinmeyen operatörlü koşulu evrak şablonu ekranında kaydetmez', function (): void {
    $admin = wp15User('Sistem Yöneticisi', 'kosul-operator-admin');
    $version = ProgramVersion::query()->firstOrFail();
    Auth::login($admin);

    Livewire::test(CreateDocTemplate::class)
        ->fillForm([
            'program_version_id' => $version->id,
            'name' => 'Kurgusal Geçersiz Operatör Belgesi',
            'is_required' => true,
            'accepted_formats' => ['pdf'],
            'sort_order' => 99,
            'condition_rules' => [[
                'field' => 'company.city',
                'op' => 'unknown',
                'list_value' => ['06'],
            ]],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(DocTemplate::query()->where('name', 'Kurgusal Geçersiz Operatör Belgesi')->exists())->toBeFalse();
});

it('çözülemeyen alan yoluna sahip koşulu evrak şablonu ekranında kaydetmez', function (): void {
    $admin = wp15User('Sistem Yöneticisi', 'kosul-alan-admin');
    $version = ProgramVersion::query()->firstOrFail();
    Auth::login($admin);

    Livewire::test(CreateDocTemplate::class)
        ->fillForm([
            'program_version_id' => $version->id,
            'name' => 'Kurgusal Geçersiz Alan Belgesi',
            'is_required' => true,
            'accepted_formats' => ['pdf'],
            'sort_order' => 100,
            'condition_rules' => [[
                'field' => 'company.unknown',
                'op' => 'in',
                'list_value' => ['06'],
            ]],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors();

    expect(DocTemplate::query()->where('name', 'Kurgusal Geçersiz Alan Belgesi')->exists())->toBeFalse();
});

it('statü renk alanında yalnız beş semantik tokena izin verir', function (): void {
    $admin = wp15User('Sistem Yöneticisi', 'renk-admin');
    Auth::login($admin);

    Livewire::test(CreateStatus::class)
        ->fillForm([
            'code' => 'invalid_color',
            'label' => 'Kurgusal Çiğ Renk',
            'type' => 'deal',
            'color' => '#ff0000',
            'sort_order' => 50,
            'is_terminal' => false,
            'change_reason' => 'Kurgusal ihlal kanıtı',
        ])
        ->call('create')
        ->assertHasFormErrors(['color']);
});

it('pazarlama rolünü bütün yönetim ekranlarında 403 ile reddeder', function (): void {
    $marketing = wp15User('Pazarlama', 'yonetim-yetkisiz');
    $resources = [
        ProgramResource::class,
        ProgramVersionResource::class,
        DocTemplateResource::class,
        StatusResource::class,
        TransitionResource::class,
        UserResource::class,
        RoleResource::class,
        BreakGlassGrantResource::class,
    ];

    foreach ($resources as $resource) {
        wp15Get($marketing, $resource::getUrl('index'))->assertForbidden();
    }
});

it('yetkili rollerin yönetim listelerini açar', function (): void {
    $admin = wp15User('Sistem Yöneticisi', 'yonetim-admin');
    $officer = wp15User('Şirket Yetkilisi', 'yonetim-yetkili');

    foreach ([ProgramResource::class, ProgramVersionResource::class, DocTemplateResource::class] as $resource) {
        wp15Get($admin, $resource::getUrl('index'))->assertOk();
    }

    foreach ([StatusResource::class, TransitionResource::class, UserResource::class, RoleResource::class] as $resource) {
        wp15Get($admin, $resource::getUrl('index'))->assertOk();
    }

    wp15Get($officer, BreakGlassGrantResource::getUrl('index'))->assertOk();
});

it('durum rozetini renk tokenı ve ayrı biçim işaretiyle birlikte üretir', function (): void {
    $badge = (string) StatusBadge::make('waiting', 'Bekliyor');

    expect($badge)->toContain('class="status-token"')
        ->and($badge)->toContain('data-status="waiting"')
        ->and($badge)->toContain('status-token__shape')
        ->and($badge)->toContain('Bekliyor');
});
