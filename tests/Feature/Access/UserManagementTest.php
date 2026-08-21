<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SaveUser;
use App\Domain\Access\Actions\UpdateRolePermissions;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Services\PageAccess;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

it('izin önbelleğinde bulunmayan yönetim işareti sayfayı 500 hatasına düşürmez', function (): void {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'eski-izin-onbellegi@example.invalid']);
    $user->assignRole('Şirket Yetkilisi');
    config()->set('access.page_management_permission', 'page.cache_stale_marker');

    expect(app(PageAccess::class)->allows($user, OperationsDashboard::class))->toBeTrue();
    $this->actingAs($user)->get(OperationsDashboard::getUrl())->assertOk();
});

it('kullanıcı pasifleştirilince tüm oturumlarını kapatır ve yeniden girişi reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'kullanici-yonetici@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $user = User::factory()->create([
        'email' => 'pasiflestirilecek@example.invalid',
        'password' => 'KurgusalParola123!',
    ]);
    $user->assignRole('Pazarlama');
    DB::table('sessions')->insert([
        'id' => 'kurgusal-wp15-oturum',
        'user_id' => $user->id,
        'payload' => 'kurgusal',
        'last_activity' => now()->timestamp,
    ]);

    app(SaveUser::class)->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => false,
        'data_scope' => null,
        'role_ids' => $user->roles()->pluck('roles.id')->all(),
        'team_ids' => [],
        'change_reason' => 'Kurgusal işten ayrılma işlemi',
    ], $actor);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->is_active)->toBeFalse()
        ->and($user->deactivated_at)->not->toBeNull();

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'KurgusalParola123!'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});

it('rol izin değişikliğini gerekçesiz kaydetmez', function (): void {
    $actor = User::factory()->create(['email' => 'rol-yonetici@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $role = Role::query()->where('name', 'Pazarlama')->firstOrFail();
    $permissions = $role->permissions()->pluck('permissions.id')->map(fn (mixed $id): int => (int) $id)->all();
    $historyCount = RolePermissionHistory::query()->count();

    expect(fn () => app(UpdateRolePermissions::class)->execute($role, $permissions, ' ', $actor))
        ->toThrow(InvalidArgumentException::class)
        ->and(RolePermissionHistory::query()->count())->toBe($historyCount);
});

it('rol izin değişikliğini gerekçesiyle salt ekleme geçmişine yazar', function (): void {
    $actor = User::factory()->create(['email' => 'rol-gecmis-yonetici@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $role = Role::query()->where('name', 'Pazarlama')->firstOrFail();
    $permissions = $role->permissions()->pluck('permissions.id')->map(fn (mixed $id): int => (int) $id)->all();

    app(UpdateRolePermissions::class)->execute($role, $permissions, 'Kurgusal görev matrisi kararı', $actor);

    expect(RolePermissionHistory::query()
        ->where('subject_type', 'role')
        ->where('subject_id', $role->id)
        ->where('reason', 'Kurgusal görev matrisi kararı')
        ->exists())->toBeTrue();
});

it('matristen alınan sayfa izninden sonra kullanıcıyı 403 ile reddeder', function (): void {
    /** @var TestCase $this */
    $actor = User::factory()->create(['email' => 'matris-yoneticisi@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $user = User::factory()->create(['email' => 'matris-kullanicisi@example.invalid']);
    $role = Role::query()->where('name', 'Pazarlama')->firstOrFail();
    $user->assignRole($role);
    $opportunityPage = Permission::findByName('page.opportunities');

    app(SaveUser::class)->execute($user, [
        'name' => $user->name,
        'email' => $user->email,
        'is_active' => true,
        'data_scope' => 'own',
        'role_ids' => [$role->id],
        'team_ids' => [],
        'page_permission_ids' => [$opportunityPage->id],
        'change_reason' => 'Firma sayfası erişimini kaldırma',
    ], $actor);

    $this->actingAs($user)->get(CompanyResource::getUrl('index'))->assertForbidden();
});

it('sayfa izni verme ve alma işlemlerini gerekçeli geçmişe yazar', function (): void {
    $actor = User::factory()->create(['email' => 'izin-gecmis-yoneticisi@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $user = User::factory()->create(['email' => 'izin-gecmis-kullanicisi@example.invalid']);
    $role = Role::query()->where('name', 'Pazarlama')->firstOrFail();
    $companyPage = Permission::findByName('page.companies');

    foreach ([[$companyPage->id], []] as $index => $permissions) {
        app(SaveUser::class)->execute($user, [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
            'data_scope' => 'own',
            'role_ids' => [$role->id],
            'team_ids' => [],
            'page_permission_ids' => $permissions,
            'change_reason' => $index === 0 ? 'Firma sayfasını görevi için verme' : 'Görev değişikliği nedeniyle geri alma',
        ], $actor);
    }

    expect(RolePermissionHistory::query()->where('subject_type', 'user')->where('subject_id', $user->id)->count())->toBe(2)
        ->and(RolePermissionHistory::query()->where('reason', 'Firma sayfasını görevi için verme')->exists())->toBeTrue()
        ->and(RolePermissionHistory::query()->where('reason', 'Görev değişikliği nedeniyle geri alma')->exists())->toBeTrue()
        ->and($user->fresh()->hasDirectPermission('page.companies'))->toBeFalse();
});

it('rolü hızlı önayar olarak kullanır ve sistem yöneticisi kendi erişimini verebilir', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['email' => 'kendi-erisim-admin@example.invalid']);
    $role = Role::query()->where('name', 'Sistem Yöneticisi')->firstOrFail();
    $admin->assignRole($role);
    $preset = app(PageAccess::class)->presetForRoles([$role->id]);
    $companyPage = Permission::findByName('page.companies');

    expect($preset)->toContain($companyPage->id);

    app(SaveUser::class)->execute($admin, [
        'name' => $admin->name,
        'email' => $admin->email,
        'is_active' => true,
        'data_scope' => 'all',
        'role_ids' => [$role->id],
        'team_ids' => [],
        'page_permission_ids' => $preset,
        'change_reason' => 'Sistem yöneticisi görev kapsamı kararı',
    ], $admin);

    $this->actingAs($admin)->get(CompanyResource::getUrl('index'))->assertOk();
});
