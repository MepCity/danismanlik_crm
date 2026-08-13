<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

function scopedUser(string $role, string $slug): User
{
    $user = User::factory()->create([
        'name' => 'Kurgusal '.$slug,
        'email' => $slug.'@example.invalid',
    ]);
    $user->assignRole($role);

    return $user;
}

/** @return array{company: Company, lead: Lead, deal: Deal, document: DealDocument, file: File} */
function scopedWorkGraph(User $owner, string $slug, ?User $pm = null): array
{
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal '.$slug.' İşletmesi',
        'city' => 'Ankara',
    ]);
    $leadStatus = Status::query()->where('type', 'lead')->firstOrFail();
    $dealStatus = Status::query()->where('type', 'deal')->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status_id' => $leadStatus->id,
    ]);
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-'.$slug,
        'status_id' => $dealStatus->id,
        'status_changed_at' => now(),
        'pm_user_id' => $pm?->id,
        'opened_by_user_id' => $owner->id,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_program_version_id' => $version->id,
        'name_snapshot' => 'Kurgusal '.$slug.' Belgesi',
        'required_snapshot' => true,
        'status' => 'requested',
    ]);
    $file = File::query()->create([
        'deal_document_id' => $document->id,
        'storage_key' => fake()->uuid(),
        'original_name' => 'kurgusal-'.$slug.'.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 128,
        'sha256' => hash('sha256', $slug),
        'version_no' => 1,
        'uploaded_by' => $owner->id,
        'scan_result' => 'clean',
    ]);

    return compact('company', 'lead', 'deal', 'document', 'file');
}

it('pazarlama listelerinde yalnız kendi açtığı iş verisini döndürür', function (): void {
    $marketing = scopedUser('Pazarlama', 'pazarlama');
    $other = scopedUser('Pazarlama', 'diger-pazarlama');
    $own = scopedWorkGraph($marketing, 'PAZ-OWN');
    $foreign = scopedWorkGraph($other, 'PAZ-FOREIGN');
    $scoper = app(ScopedQuery::class);

    expect($scoper->apply(Company::query(), $marketing)->pluck('id')->all())->toBe([$own['company']->id])
        ->and($scoper->apply(Lead::query(), $marketing)->pluck('id')->all())->toBe([$own['lead']->id])
        ->and($scoper->apply(Deal::query(), $marketing)->pluck('id')->all())->toBe([$own['deal']->id])
        ->and($scoper->apply(Deal::query(), $marketing)->whereKey($foreign['deal']->id)->exists())->toBeFalse();
});

it('ham sorguyu sistem işleri için açık bırakır ve kullanıcı listesini yalnız ScopedQuery ile daraltır', function (): void {
    $marketing = scopedUser('Pazarlama', 'acik-scope');
    scopedWorkGraph($marketing, 'EXPLICIT-OWN');
    scopedWorkGraph(scopedUser('Pazarlama', 'acik-scope-diger'), 'EXPLICIT-OTHER');

    expect(Deal::query()->count())->toBe(2)
        ->and(app(ScopedQuery::class)->apply(Deal::query(), $marketing)->count())->toBe(1);
});

it('proje yöneticisi kendi ekibinin dosyalarını görür ve diğer ekibi görmez', function (): void {
    $pm = scopedUser('Proje Yöneticisi', 'pm');
    $teammate = scopedUser('Pazarlama', 'ekip-arkadasi');
    $outsider = scopedUser('Pazarlama', 'baska-ekip');
    $team = Team::query()->create(['name' => 'Kurgusal Birinci Ekip', 'manager_id' => $pm->id]);
    $team->members()->attach([$pm->id => ['role' => 'manager'], $teammate->id => ['role' => 'member']]);
    $own = scopedWorkGraph($pm, 'PM-OWN', $pm);
    $teamDeal = scopedWorkGraph($teammate, 'PM-TEAM', $teammate);
    $foreign = scopedWorkGraph($outsider, 'PM-FOREIGN', $outsider);

    $ids = app(ScopedQuery::class)->apply(Deal::query(), $pm)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe([$own['deal']->id, $teamDeal['deal']->id])
        ->and($ids)->not->toContain($foreign['deal']->id);
});

it('şirket yetkilisi tüm iş verisi listelerini görür', function (): void {
    $officer = scopedUser('Şirket Yetkilisi', 'yetkili');
    $first = scopedWorkGraph(scopedUser('Pazarlama', 'birinci'), 'ALL-ONE');
    $second = scopedWorkGraph(scopedUser('Pazarlama', 'ikinci'), 'ALL-TWO');

    $ids = app(ScopedQuery::class)->apply(Deal::query(), $officer)->orderBy('id')->pluck('id')->all();

    expect($ids)->toBe([$first['deal']->id, $second['deal']->id]);
});

it('sistem yöneticisinin iş verisi listelerini hata vermeden boş döndürür', function (): void {
    $admin = scopedUser('Sistem Yöneticisi', 'sistem-yoneticisi');
    scopedWorkGraph(scopedUser('Pazarlama', 'veri-sahibi'), 'NONE');
    $scoper = app(ScopedQuery::class);

    expect($scoper->apply(Company::query(), $admin)->get())->toBeEmpty()
        ->and($scoper->apply(Lead::query(), $admin)->get())->toBeEmpty()
        ->and($scoper->apply(Deal::query(), $admin)->get())->toBeEmpty();
});

it('kapsam dışı id ile doğrudan erişimi policy katmanında reddeder', function (): void {
    $marketing = scopedUser('Pazarlama', 'idor-pazarlama');
    $foreign = scopedWorkGraph(scopedUser('Pazarlama', 'idor-diger'), 'IDOR');

    expect(Gate::forUser($marketing)->allows('view', $foreign['deal']))->toBeFalse();
});

it('kullanıcı kapsam ezmesini uygular ve çok rolde en dar kapsamı seçer', function (): void {
    $overridden = scopedUser('Pazarlama', 'kapsam-ezme');
    $overridden->update(['data_scope' => 'all']);
    $multiRole = scopedUser('Şirket Yetkilisi', 'cok-rol');
    $multiRole->assignRole('Pazarlama');
    $foreign = scopedWorkGraph(scopedUser('Pazarlama', 'kapsam-sahibi'), 'SCOPE');
    $scoper = app(ScopedQuery::class);

    expect($scoper->apply(Deal::query(), $overridden)->whereKey($foreign['deal']->id)->exists())->toBeTrue()
        ->and($scoper->apply(Deal::query(), $multiRole)->whereKey($foreign['deal']->id)->exists())->toBeFalse();
});

it('her varlıkta yetkisiz mutasyonu ve tanımsız eylemi varsayılan olarak reddeder', function (): void {
    $unauthorized = User::factory()->create(['email' => 'yetkisiz@example.invalid']);
    $models = [
        new Company, new Contact, new Lead, new Interaction, new Deal,
        new DealDocument, new File, new Program, new ProgramVersion,
        new DocTemplate, new Comment, new Task,
    ];

    foreach ($models as $model) {
        expect(Gate::forUser($unauthorized)->allows('viewAny', $model::class))->toBeFalse()
            ->and(Gate::forUser($unauthorized)->allows('create', $model::class))->toBeFalse()
            ->and(Gate::forUser($unauthorized)->allows('view', $model))->toBeFalse()
            ->and(Gate::forUser($unauthorized)->allows('update', $model))->toBeFalse()
            ->and(Gate::forUser($unauthorized)->allows('deactivate', $model))->toBeFalse()
            ->and(Gate::forUser($unauthorized)->allows('delete', $model))->toBeFalse();
    }

    expect(Gate::forUser($unauthorized)->allows('export-unregistered', new Deal))->toBeFalse();
});

it('sistem yöneticisine yapılandırma izni verirken iş verisi kapsamını açmaz', function (): void {
    $admin = scopedUser('Sistem Yöneticisi', 'yapilandirma-admin');
    $program = Program::query()->firstOrFail();

    expect(Gate::forUser($admin)->allows('view', $program))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $program))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewAny', Deal::class))->toBeFalse();
});

it('sistem yöneticisinin belge indirmesini break glass olmadan reddeder', function (): void {
    $admin = scopedUser('Sistem Yöneticisi', 'belge-admin');
    $graph = scopedWorkGraph(scopedUser('Pazarlama', 'belge-sahibi'), 'FILE-DENY');

    expect($admin->can('document.download'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('download', $graph['file']))->toBeFalse();
});
