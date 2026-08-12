<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Reporting\Enums\ReportType;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\Reports;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    app()->setLocale('tr');
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

function reportingScreenUser(string $role, string $suffix): User
{
    $user = User::factory()->create([
        'name' => "Kurgusal {$suffix}",
        'email' => strtolower($suffix).'@example.invalid',
    ]);
    $user->assignRole($role);

    return $user;
}

function reportingScreenDeal(User $owner, string $suffix, ?User $pm = null): Deal
{
    $company = Company::query()->create(['legal_name' => "Kurgusal {$suffix}", 'city' => '06']);

    return Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => ProgramVersion::query()->firstOrFail()->id,
        'reference_no' => "RS-{$suffix}",
        'status_id' => Status::query()->where('type', 'deal')->where('is_initial', true)->sole()->id,
        'status_changed_at' => now(),
        'pm_user_id' => $pm?->id,
        'opened_by_user_id' => $owner->id,
    ]);
}

it('proje yöneticisinin role uygun ana panel kartlarını gösterir', function (): void {
    $pm = reportingScreenUser('Proje Yöneticisi', 'Ekran PM');
    reportingScreenDeal($pm, 'EKRAN-PM', $pm);
    Auth::login($pm);

    Livewire::test(OperationsDashboard::class)
        ->assertSee('Bana atanan yeni işler')
        ->assertSee('Belgesi eksik dosyalar')
        ->assertDontSee('PM atanmamış işler');
});

it('sistem yöneticisinin ana panelini iş verisi göstermeden güvenli boş durumda açar', function (): void {
    $admin = reportingScreenUser('Sistem Yöneticisi', 'Ekran Admin');
    reportingScreenDeal(reportingScreenUser('Pazarlama', 'Ekran Veri'), 'EKRAN-VERI');
    Auth::login($admin);

    Livewire::test(OperationsDashboard::class)
        ->assertSee('Bu rolün iş verisi kapsamı yok')
        ->assertDontSee('PM atanmamış işler')
        ->assertSee('Kapsamınızda henüz aktivite yok');
});

it('üç operasyon raporu arasında geçiş yapar ve excel yetkisini sunumda ayırır', function (): void {
    $marketing = reportingScreenUser('Pazarlama', 'Ekran Rapor');
    reportingScreenDeal($marketing, 'EKRAN-RAPOR');
    Auth::login($marketing);

    Livewire::test(Reports::class)
        ->assertSee('Dosya panosu')
        ->call('selectReport', ReportType::PendingAssignments->value)
        ->assertSee('Bekleyen atamalar')
        ->call('selectReport', ReportType::MissingDocuments->value)
        ->assertSee('Eksik evrak listesi')
        ->call('selectReport', ReportType::ConversionFunnel->value)
        ->assertSee('Dönüşüm hunisi')
        ->assertSee('Excel indirme izni gerekli');
});
