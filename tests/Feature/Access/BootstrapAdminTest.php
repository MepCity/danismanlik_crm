<?php

declare(strict_types=1);

use App\Domain\Access\Actions\BootstrapAdmin;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('güvenli ilk sistem yöneticisini doğru rol ve yetkilerle oluşturur', function (): void {
    /** @var TestCase $this */
    putenv('ADMIN_BOOTSTRAP_PASSWORD=GucluParola123!');
    $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] = 'GucluParola123!';

    $this->artisan('system:bootstrap-admin', [
        '--name' => 'İlk Yönetici',
        '--email' => 'ilk-admin@bizlife.invalid',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ilk-admin@bizlife.invalid')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('İlk Yönetici')
        ->and($user->data_scope)->toBe('none')
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole('Sistem Yöneticisi'))->toBeTrue()
        ->and(Hash::check('GucluParola123!', $user->password))->toBeTrue();

    // History check - password must not be in payload
    $history = RolePermissionHistory::query()
        ->where('subject_type', 'user')
        ->where('subject_id', $user->id)
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->change_type)->toBe('bootstrap_admin_created')
        ->and(json_encode($history->new_value))->not->toContain('GucluParola123!');
});

it('etkin sistem yöneticisi varken varsayılan olarak reddeder', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['email' => 'mevcut-admin@bizlife.invalid', 'is_active' => true]);
    $admin->assignRole('Sistem Yöneticisi');

    putenv('ADMIN_BOOTSTRAP_PASSWORD=GucluParola123!');
    $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] = 'GucluParola123!';

    $this->artisan('system:bootstrap-admin', [
        '--name' => 'İkinci Yönetici',
        '--email' => 'ikinci-admin@bizlife.invalid',
    ])->assertFailed();

    expect(User::query()->where('email', 'ikinci-admin@bizlife.invalid')->exists())->toBeFalse();
});

it('force bayrağı verildiğinde mevcut yönetici olsa bile oluşturur', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['email' => 'mevcut-admin@bizlife.invalid', 'is_active' => true]);
    $admin->assignRole('Sistem Yöneticisi');

    putenv('ADMIN_BOOTSTRAP_PASSWORD=GucluParola123!');
    $_ENV['ADMIN_BOOTSTRAP_PASSWORD'] = 'GucluParola123!';

    $this->artisan('system:bootstrap-admin', [
        '--name' => 'İkinci Yönetici',
        '--email' => 'ikinci-admin@bizlife.invalid',
        '--force' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', 'ikinci-admin@bizlife.invalid')->exists())->toBeTrue();
});

it('büyük harf, küçük harf, rakam veya sembol içermeyen zayıf parolaları reddeder', function (): void {
    $action = app(BootstrapAdmin::class);

    // Kisa parola (<12)
    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'a@b.com', 'password' => 'Kisa1!']))
        ->toThrow(InvalidArgumentException::class);

    // Buyuk harf yok
    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'a@b.com', 'password' => 'kucukharfparola123!']))
        ->toThrow(InvalidArgumentException::class);

    // Kucuk harf yok
    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'a@b.com', 'password' => 'BUYUKHARFPAROLA123!']))
        ->toThrow(InvalidArgumentException::class);

    // Rakam yok
    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'a@b.com', 'password' => 'HarflerVeSemboller!']))
        ->toThrow(InvalidArgumentException::class);

    // Sembol yok
    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'a@b.com', 'password' => 'HarflerVeRakamlar123']))
        ->toThrow(InvalidArgumentException::class);
});

it('boş isim ve geçersiz e-posta durumunda hata fırlatır', function (): void {
    $action = app(BootstrapAdmin::class);

    expect(fn () => $action->execute(['name' => '', 'email' => 'a@b.com', 'password' => 'GucluParola123!']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $action->execute(['name' => 'Admin', 'email' => 'gecersiz-eposta', 'password' => 'GucluParola123!']))
        ->toThrow(InvalidArgumentException::class);
});
