<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SaveUser;
use App\Domain\Access\Actions\UpdateRolePermissions;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Filament\Resources\BreakGlassGrants\Pages\ListBreakGlassGrants;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
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

it('acil erişim ekranında altmış dakikanın üstünü doğrulamada reddeder', function (): void {
    $officer = User::factory()->create(['email' => 'acil-ekran-yetkili@example.invalid']);
    $officer->assignRole('Şirket Yetkilisi');
    $admin = User::factory()->create(['email' => 'acil-ekran-admin@example.invalid']);
    $admin->assignRole('Sistem Yöneticisi');
    Auth::login($officer);

    Livewire::test(ListBreakGlassGrants::class)
        ->callTableAction('grant', null, [
            'user_id' => $admin->id,
            'reason' => 'Kurgusal acil bakım',
            'duration_minutes' => 61,
        ])
        ->assertHasTableActionErrors(['duration_minutes']);
});
