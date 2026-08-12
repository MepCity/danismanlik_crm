<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Reporting\Enums\ReportType;
use App\Filament\Pages\DealBoard;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\Reports;
use App\Filament\Pages\TodayCalls;
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

it('ana panelde dolu uyarıları formla öne çıkarır ve sıfırları nötr tutar', function (): void {
    $officer = reportingScreenUser('Şirket Yetkilisi', 'Kart Durumu');
    reportingScreenDeal($officer, 'ATANMAMIS');
    Auth::login($officer);

    $html = Livewire::test(OperationsDashboard::class)->html();

    expect($html)
        ->toMatch('/data-testid="dashboard-card-unassigned_deals"[^>]+data-state="danger"/')
        ->toMatch('/data-testid="dashboard-card-upcoming_deadlines"[^>]+data-state="neutral"/')
        ->toContain('dashboard-card__stripe', 'dashboard-card__icon', 'dashboard-card__status');
});

it('ana panel kartlarını ilgili filtreli listelere bağlar', function (): void {
    $page = app(OperationsDashboard::class);

    expect($page->cardUrl('today_calls'))->toBe(TodayCalls::getUrl(['filter' => 'today']))
        ->and($page->cardUrl('overdue_followups'))->toBe(TodayCalls::getUrl(['filter' => 'overdue']))
        ->and($page->cardUrl('new_assignments'))->toBe(DealBoard::getUrl(['filter' => 'new_assignments']))
        ->and($page->cardUrl('missing_documents'))->toBe(Reports::getUrl(['report' => ReportType::MissingDocuments->value]))
        ->and($page->cardUrl('upcoming_deadlines'))->toBe(Reports::getUrl(['report' => ReportType::UpcomingDeadlines->value]))
        ->and($page->cardUrl('customer_response'))->toBe(DealBoard::getUrl(['filter' => 'customer_response']))
        ->and($page->cardUrl('unassigned_deals'))->toBe(Reports::getUrl(['report' => ReportType::PendingAssignments->value]));
});

it('geciken takip kartının hedefinde yalnız geciken kayıtları gösterir', function (): void {
    $officer = reportingScreenUser('Şirket Yetkilisi', 'Filtre Yetkili');
    $owner = reportingScreenUser('Pazarlama', 'Filtre Pazarlama');
    $status = Status::query()->where('type', 'lead')->where('is_initial', true)->sole();
    $overdueCompany = Company::query()->create(['legal_name' => 'Kurgusal Geciken Takip', 'city' => '06']);
    $todayCompany = Company::query()->create(['legal_name' => 'Kurgusal Bugünkü Takip', 'city' => '06']);

    foreach ([[$overdueCompany, now()->subDay()], [$todayCompany, now()->addHour()]] as [$company, $nextCallAt]) {
        Lead::query()->create([
            'company_id' => $company->id,
            'owner_user_id' => $owner->id,
            'status_id' => $status->id,
            'next_call_at' => $nextCallAt,
        ]);
    }
    Auth::login($officer);

    Livewire::test(TodayCalls::class)
        ->set('filter', 'overdue')
        ->assertSet('filter', 'overdue')
        ->assertSee('Etkin filtre: Geciken takipler')
        ->assertSee($overdueCompany->legal_name)
        ->assertDontSee($todayCompany->legal_name);
});

it('rapor kartının hedefinde seçili sabit raporu açar', function (): void {
    $officer = reportingScreenUser('Şirket Yetkilisi', 'Rapor Filtre');
    Auth::login($officer);

    Livewire::test(Reports::class)
        ->set('activeReport', ReportType::MissingDocuments->value)
        ->assertSet('activeReport', ReportType::MissingDocuments->value)
        ->assertSee('Eksik evrak listesi');
});

it('yeni atama kartının hedefinde yalnız kullanıcıya yeni atanan dosyaları gösterir', function (): void {
    $officer = reportingScreenUser('Şirket Yetkilisi', 'Yeni Atama Yetkili');
    $other = reportingScreenUser('Proje Yöneticisi', 'Yeni Atama Diğer');
    $assigned = reportingScreenDeal($officer, 'YENI-ATAMA', $officer);
    $foreign = reportingScreenDeal($officer, 'BASKA-ATAMA', $other);
    Auth::login($officer);

    Livewire::test(DealBoard::class)
        ->set('filter', 'new_assignments')
        ->assertSet('filter', 'new_assignments')
        ->assertSee('Etkin filtre: Yeni atanan işler')
        ->assertSee($assigned->company->legal_name)
        ->assertDontSee($foreign->company->legal_name);
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
