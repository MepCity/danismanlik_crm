<?php

declare(strict_types=1);

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\Program;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('takım oluşturma yöneticisi role=manager üyeleri role=member olarak ve gerekçeli geçmiş kaydeder', function (): void {
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
        ->and($team->members()->count())->toBe(3)
        ->and($team->is_active)->toBeTrue();

    $managerRole = DB::table('team_members')
        ->where('team_id', $team->id)
        ->where('user_id', $manager->id)
        ->value('role');

    $member1Role = DB::table('team_members')
        ->where('team_id', $team->id)
        ->where('user_id', $member1->id)
        ->value('role');

    expect($managerRole)->toBe('manager')
        ->and($member1Role)->toBe('member');

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

it('gerekçesiz veya geçersiz üyeli takım işlemini reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'takim-admin-3@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $manager = User::factory()->create(['email' => 'lider-3@example.invalid', 'is_active' => true]);

    expect(fn () => app(SaveTeam::class)->execute(null, [
        'name' => 'Gerekçesiz Takım',
        'manager_id' => $manager->id,
        'change_reason' => '   ',
    ], $actor))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(SaveTeam::class)->execute(null, [
        'name' => 'Geçersiz Üyeli Takım',
        'manager_id' => $manager->id,
        'member_ids' => [999999],
        'change_reason' => 'Hatalı ID ile deneme',
    ], $actor))->toThrow(InvalidArgumentException::class);
});

it('yönetici ve üyelik değişikliği ScopedQuery team görünürlüğünü dinamik olarak günceller', function (): void {
    $actor = User::factory()->create(['email' => 'admin-scope-test@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    $pm = User::factory()->create(['email' => 'pm-scope-test@example.invalid', 'data_scope' => 'team']);
    $pm->assignRole('Proje Yöneticisi');

    $marketing = User::factory()->create(['email' => 'marketing-scope-test@example.invalid', 'data_scope' => 'own']);
    $marketing->assignRole('Pazarlama');

    $company = Company::query()->create([
        'legal_name' => 'Takım Kapsamı Deneme A.Ş.',
        'industry' => 'technology',
        'owner_user_id' => $marketing->id,
        'is_active' => true,
    ]);

    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
    $version = $program->versions()->firstOrFail();
    $status = Status::query()->where('type', 'deal')->firstOrFail();

    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $marketing->id,
        'pm_user_id' => null,
        'reference_no' => 'TKM-2026-001',
    ]);

    $scopedQuery = app(ScopedQuery::class);

    // 1. Durum: PM ve Marketing aynı takımda değilken PM dosyayı göremez
    $visibleDealsBefore = $scopedQuery->apply(Deal::query(), $pm, 'deal.view')->get();
    expect($visibleDealsBefore->pluck('id')->all())->not->toContain($deal->id);

    // 2. Durum: Marketing kullanıcısı PM'in takımına eklendiğinde PM dosyayı team kapsamında görür
    $team = app(SaveTeam::class)->execute(null, [
        'name' => 'Dinamik Test Takımı',
        'manager_id' => $pm->id,
        'member_ids' => [$marketing->id],
        'is_active' => true,
        'change_reason' => 'Takım kapsamı testi için ekleme',
    ], $actor);

    $visibleDealsAfterJoin = $scopedQuery->apply(Deal::query(), $pm, 'deal.view')->get();
    expect($visibleDealsAfterJoin->pluck('id')->all())->toContain($deal->id);

    // 3. Durum: Marketing takımdan çıkarıldığında PM'in görünürlüğünden derhal düşer
    app(SaveTeam::class)->execute($team, [
        'name' => 'Dinamik Test Takımı',
        'manager_id' => $pm->id,
        'member_ids' => [],
        'is_active' => true,
        'change_reason' => 'Takım kapsamı testi için çıkarma',
    ], $actor);

    $visibleDealsAfterLeave = $scopedQuery->apply(Deal::query(), $pm, 'deal.view')->get();
    expect($visibleDealsAfterLeave->pluck('id')->all())->not->toContain($deal->id);
});
