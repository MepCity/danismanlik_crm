<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
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

it('sistem yöneticisi takım ve rol yönetim ekranlarına erişebilir', function (): void {
    /** @var TestCase $this */
    $admin = User::factory()->create(['email' => 'admin-erisim@example.invalid']);
    $admin->assignRole('Sistem Yöneticisi');

    $this->actingAs($admin)->get(TeamResource::getUrl('index'))->assertOk();
    $this->actingAs($admin)->get(RoleResource::getUrl('index'))->assertOk();
    $this->actingAs($admin)->get(UserResource::getUrl('index'))->assertOk();
});

it('şirket yetkilisi izin verilmediyse takım ve rol ekranlarına doğrudan URL ile erişemez', function (): void {
    /** @var TestCase $this */
    $authority = User::factory()->create(['email' => 'yetkili-erisim@example.invalid']);
    $authority->assignRole('Şirket Yetkilisi');

    $this->actingAs($authority)->get(TeamResource::getUrl('index'))->assertForbidden();
    $this->actingAs($authority)->get(RoleResource::getUrl('index'))->assertForbidden();
    $this->actingAs($authority)->get(UserResource::getUrl('index'))->assertForbidden();
});

it('takım oluşturma yöneticisi ve üyeleriyle gerekçeli geçmiş kaydeder', function (): void {
    $actor = User::factory()->create(['email' => 'takim-admin@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    $manager = User::factory()->create(['email' => 'takim-lideri@example.invalid', 'is_active' => true]);
    $member1 = User::factory()->create(['email' => 'uye1@example.invalid', 'is_active' => true]);
    $member2 = User::factory()->create(['email' => 'uye2@example.invalid', 'is_active' => true]);

    $team = app(SaveTeam::class)->execute(null, [
        'name' => 'Kurgusal Marmara Ekibi',
        'manager_id' => $manager->id,
        'member_ids' => [$member1->id, $member2->id],
        'is_active' => true,
        'change_reason' => 'Bölgesel operasyon takımı kurulumu',
    ], $actor);

    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('Kurgusal Marmara Ekibi')
        ->and($team->manager_id)->toBe($manager->id)
        ->and($team->members()->count())->toBe(2)
        ->and($team->is_active)->toBeTrue();

    $history = RolePermissionHistory::query()
        ->where('subject_type', 'team')
        ->where('subject_id', $team->id)
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->change_type)->toBe('team_created')
        ->and($history->reason)->toBe('Bölgesel operasyon takımı kurulumu')
        ->and($history->changed_by)->toBe($actor->id);
});

it('takım pasifleştirilince kaydı silmez ve geçmişe yazar', function (): void {
    $actor = User::factory()->create(['email' => 'takim-admin-2@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    $manager = User::factory()->create(['email' => 'lider-2@example.invalid', 'is_active' => true]);
    $team = Team::query()->create([
        'name' => 'Kurgusal Ege Ekibi',
        'manager_id' => $manager->id,
        'is_active' => true,
    ]);

    $updatedTeam = app(SaveTeam::class)->execute($team, [
        'name' => 'Kurgusal Ege Ekibi',
        'manager_id' => $manager->id,
        'member_ids' => [],
        'is_active' => false,
        'change_reason' => 'Sezon sonu ekip pasifleştirme',
    ], $actor);

    expect($updatedTeam->is_active)->toBeFalse()
        ->and(Team::query()->whereKey($team->id)->exists())->toBeTrue();

    $history = RolePermissionHistory::query()
        ->where('subject_type', 'team')
        ->where('subject_id', $team->id)
        ->where('change_type', 'team_updated')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history->reason)->toBe('Sezon sonu ekip pasifleştirme');
});

it('gerekçesiz takım işlemini reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'takim-admin-3@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $manager = User::factory()->create(['email' => 'lider-3@example.invalid', 'is_active' => true]);

    expect(fn () => app(SaveTeam::class)->execute(null, [
        'name' => 'Gerekçesiz Takım',
        'manager_id' => $manager->id,
        'change_reason' => '   ',
    ], $actor))->toThrow(InvalidArgumentException::class);
});
