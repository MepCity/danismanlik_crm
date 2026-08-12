<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\DashboardQuery;
use App\Domain\Reporting\Services\ExcelReportExporter;
use App\Domain\Reporting\Services\ReportQuery;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

function reportingUser(string $role, string $suffix): User
{
    $user = User::factory()->create([
        'name' => "Kurgusal {$suffix}",
        'email' => strtolower($suffix).'@example.invalid',
    ]);
    $user->assignRole($role);

    return $user;
}

/** @return array{deal: Deal, company: Company} */
function reportingDeal(User $owner, string $suffix, ?User $pm = null, ?string $result = null): array
{
    $company = Company::query()->create([
        'legal_name' => "Kurgusal {$suffix} İşletmesi",
        'city' => '06',
    ]);
    $status = Status::query()->where('type', 'deal')->where('is_initial', true)->sole();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => ProgramVersion::query()->firstOrFail()->id,
        'reference_no' => "R-{$suffix}",
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'pm_user_id' => $pm?->id,
        'opened_by_user_id' => $owner->id,
        'result_outcome' => $result,
    ]);

    return compact('deal', 'company');
}

/** @return list<list<mixed>> */
function spreadsheetRows(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

it('aynı statüye iki kez girilen dosyanın toplam süresini status history üzerinden hesaplar', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Süre Yetkili');
    $fixture = reportingDeal($officer, 'SURE');
    $status = $fixture['deal']->status;
    $revisionId = DB::table('workflow_revisions')->value('id');
    $fixture['deal']->update(['status_changed_at' => now()->subMinute()]);
    StatusHistory::query()->create([
        'deal_id' => $fixture['deal']->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'workflow_revision_id' => $revisionId,
        'entered_at' => now()->subDays(10),
        'exited_at' => now()->subDays(8),
        'changed_by' => $officer->id,
    ]);
    StatusHistory::query()->create([
        'deal_id' => $fixture['deal']->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'workflow_revision_id' => $revisionId,
        'entered_at' => now()->subDays(5),
        'exited_at' => now()->subDays(2),
        'changed_by' => $officer->id,
    ]);

    $row = app(ReportQuery::class)->table(ReportType::DealBoard, $officer)->rows->sole();

    expect((float) $row->status_days)->toBe(5.0);
});

it('evrak toplama süresini statüden değil süreç damgalarından hesaplar', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Damga Yetkili');
    $fixture = reportingDeal($officer, 'DAMGA');
    $fixture['deal']->update([
        'document_requested_at' => '2026-08-01 09:00:00',
        'all_required_accepted_at' => '2026-08-04 21:00:00',
        'status_changed_at' => '2026-01-01 00:00:00',
    ]);

    $row = app(ReportQuery::class)->table(ReportType::DealBoard, $officer)->rows->sole();

    expect((float) $row->document_collection_days)->toBe(3.5);
});

it('dönüşüm hunisinde aramayı sonuçla birlikte ve başarıyı program bazında gösterir', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Huni Yetkili');
    $owner = reportingUser('Pazarlama', 'Huni Sahip');
    $approved = reportingDeal($owner, 'ONAY', result: 'approved')['deal'];
    $rejected = reportingDeal($owner, 'RET', result: 'rejected')['deal'];
    $leadStatus = Status::query()->where('type', 'lead')->where('is_initial', true)->sole();
    $version = ProgramVersion::query()->firstOrFail();

    foreach ([[$approved, 'A'], [$rejected, 'B']] as [$deal, $suffix]) {
        $lead = Lead::query()->create([
            'company_id' => $deal->company_id,
            'owner_user_id' => $owner->id,
            'interested_program_version_id' => $version->id,
            'status_id' => $leadStatus->id,
            'converted_deal_id' => $deal->id,
        ]);
        Interaction::query()->create([
            'lead_id' => $lead->id,
            'user_id' => $owner->id,
            'type' => 'call',
            'direction' => 'outbound',
            'purpose' => 'marketing',
            'occurred_at' => now(),
            'outcome' => $suffix === 'A' ? 'contacted' : 'unreachable',
        ]);
    }
    Interaction::query()->create([
        'lead_id' => Lead::query()->firstOrFail()->id,
        'user_id' => $owner->id,
        'type' => 'call',
        'direction' => 'outbound',
        'purpose' => 'marketing',
        'occurred_at' => now(),
        'outcome' => 'interested',
    ]);

    $row = app(ReportQuery::class)->table(ReportType::ConversionFunnel, $officer)->rows->sole();

    expect((int) $row->call_count)->toBe(3)
        ->and((int) $row->conversation_count)->toBe(2)
        ->and((int) $row->converted_count)->toBe(2)
        ->and((int) $row->approved_count)->toBe(1)
        ->and((float) $row->call_to_conversation_rate)->toBe(66.7)
        ->and((float) $row->approval_rate)->toBe(50.0);
});

it('excel dışa aktarımında kapsam dışı satırı içermez', function (): void {
    $owner = reportingUser('Pazarlama', 'Excel Sahip');
    $owner->givePermissionTo('report.export');
    $other = reportingUser('Pazarlama', 'Excel Diğer');
    $own = reportingDeal($owner, 'EXCEL-OWN');
    $foreign = reportingDeal($other, 'EXCEL-FOREIGN');

    $export = app(ExcelReportExporter::class)->export(ReportType::DealBoard, $owner);
    $content = collect(spreadsheetRows($export['path']))->flatten()->implode('|');

    expect($content)->toContain($own['company']->legal_name);
    expect($content)->not->toContain($foreign['company']->legal_name);
    expect($export['row_count'])->toBe(1);
    unlink($export['path']);
});

it('ayrı dışa aktarma izni olmadan indirmeyi reddeder', function (): void {
    /** @var TestCase $this */
    $marketing = reportingUser('Pazarlama', 'İzinsiz Excel');

    $this->actingAs($marketing)
        ->get(route('reports.export', ['report' => ReportType::DealBoard->value]))
        ->assertForbidden();
});

it('servis doğrudan çağrıldığında dışa aktarma iznini zorunlu tutar', function (): void {
    $marketing = reportingUser('Pazarlama', 'Servis İzinsiz Excel');

    expect(fn () => app(ExcelReportExporter::class)->export(ReportType::DealBoard, $marketing))
        ->toThrow(AuthorizationException::class, 'Bu raporu Excel olarak dışa aktarma izniniz yok.')
        ->and(ReportExport::query()->count())->toBe(0);
});

it('servis doğrudan çağrıldığında izinli kullanıcı için excel üretir', function (): void {
    $marketing = reportingUser('Pazarlama', 'Servis İzinli Excel');
    $marketing->givePermissionTo('report.export');
    reportingDeal($marketing, 'SERVICE-ALLOWED');

    $export = app(ExcelReportExporter::class)->export(ReportType::DealBoard, $marketing);

    expect(is_file($export['path']))->toBeTrue()
        ->and($export['row_count'])->toBe(1)
        ->and(ReportExport::query()->where('actor_id', $marketing->id)->exists())->toBeTrue();
    unlink($export['path']);
});

it('dışa aktarımı satır sayısıyla salt ekleme kaydına ve audit loga yazar', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Denetim Yetkili');
    reportingDeal($officer, 'AUDIT-EXPORT');

    $export = app(ExcelReportExporter::class)->export(ReportType::DealBoard, $officer);
    $record = ReportExport::query()->sole();

    expect($record->actor_id)->toBe($officer->id)
        ->and($record->report_code)->toBe(ReportType::DealBoard->value)
        ->and($record->row_count)->toBe(1)
        ->and(DB::table('audit_log')->where('table_name', 'report_exports')->where('row_id', $record->id)->exists())->toBeTrue();
    unlink($export['path']);
});

it('altı sabit raporun tamamını excel olarak üretebilir', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Altı Rapor');
    reportingDeal($officer, 'ALTI');

    foreach (ReportType::cases() as $type) {
        $export = app(ExcelReportExporter::class)->export($type, $officer);
        expect(is_file($export['path']))->toBeTrue()
            ->and(spreadsheetRows($export['path'])[0])->not->toBeEmpty();
        unlink($export['path']);
    }
});

it('rol panellerindeki iş kartlarını kapsamdan geçirir ve sistem yöneticisini boş bırakır', function (): void {
    $marketing = reportingUser('Pazarlama', 'Panel Pazarlama');
    $other = reportingUser('Pazarlama', 'Panel Diğer');
    $pm = reportingUser('Proje Yöneticisi', 'Panel PM');
    $officer = reportingUser('Şirket Yetkilisi', 'Panel Yetkili');
    $admin = reportingUser('Sistem Yöneticisi', 'Panel Admin');
    $leadStatus = Status::query()->where('type', 'lead')->where('is_initial', true)->sole();
    foreach ([$marketing, $other] as $owner) {
        $company = Company::query()->create(['legal_name' => "Kurgusal {$owner->id}", 'city' => '34']);
        Lead::query()->create([
            'company_id' => $company->id,
            'owner_user_id' => $owner->id,
            'status_id' => $leadStatus->id,
            'next_call_at' => now()->addHour(),
        ]);
    }
    reportingDeal($pm, 'PANEL-PM', $pm);
    reportingDeal($other, 'PANEL-OTHER');
    $dashboard = app(DashboardQuery::class);

    $marketingCards = collect($dashboard->cards($marketing))->keyBy('key');
    $pmCards = collect($dashboard->cards($pm))->keyBy('key');
    $officerCards = collect($dashboard->cards($officer))->keyBy('key');

    expect($marketingCards['today_calls']['count'])->toBe(1)
        ->and($pmCards['new_assignments']['count'])->toBe(1)
        ->and($officerCards['unassigned_deals']['count'])->toBe(1)
        ->and($dashboard->cards($admin))->toBe([])
        ->and($dashboard->recentActivities($admin))->toBeEmpty();
});

it('rapor sorgu sayısını satır sayısından bağımsız ve boş veri setini güvenli tutar', function (): void {
    $officer = reportingUser('Şirket Yetkilisi', 'Sorgu Yetkili');
    $reports = app(ReportQuery::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $empty = $reports->table(ReportType::DealBoard, $officer);
    $emptyQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(1, 12) as $index) {
        reportingDeal($officer, "QUERY-{$index}");
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $filled = $reports->table(ReportType::DealBoard, $officer);
    $filledQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($empty->rows)->toBeEmpty()
        ->and($empty->total)->toBe(0)
        ->and($filled->rows)->toHaveCount(12)
        ->and($filledQueries)->toBe($emptyQueries)
        ->and($filledQueries)->toBe(5);
});

it('statü süre toplama indeksini ve salt ekleme dışa aktarım tablosunu kurar', function (): void {
    $index = DB::table('pg_indexes')
        ->where('schemaname', DB::raw('current_schema()'))
        ->where('tablename', 'status_history')
        ->where('indexname', 'status_history_deal_status_duration')
        ->value('indexdef');

    expect($index)->toContain('(deal_id, status_id, entered_at) INCLUDE (exited_at)')
        ->and(DB::table('information_schema.tables')->where('table_schema', DB::raw('current_schema()'))->where('table_name', 'report_exports')->exists())->toBeTrue();
});
