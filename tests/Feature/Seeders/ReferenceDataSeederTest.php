<?php

declare(strict_types=1);

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('referans verisini iki çalıştırmada çoğaltmaz', function (): void {
    $tables = [
        'users', 'statuses', 'transitions', 'workflow_revisions', 'permissions',
        'roles', 'role_has_permissions', 'programs', 'program_versions', 'doc_templates',
    ];
    $before = collect($tables)->mapWithKeys(
        fn (string $table): array => [$table => DB::table($table)->count()],
    )->all();

    (new ReferenceDataSeeder)->setContainer(app())->run();

    $after = collect($tables)->mapWithKeys(
        fn (string $table): array => [$table => DB::table($table)->count()],
    )->all();

    expect($after)->toBe($before);
});

it('yalnız tanımlı fırsat ve dosya statülerini kurar', function (): void {
    expect(Status::query()->where('type', 'lead')->count())->toBe(8)
        ->and(Status::query()->where('type', 'deal')->count())->toBe(10)
        ->and(Status::query()->whereIn('code', ['partially_received', 'documents_missing'])->exists())->toBeFalse();
});

it('terminal olmayan her statüye etkin çıkış geçişi verir', function (): void {
    $orphans = Status::query()
        ->where('is_active', true)
        ->where('is_terminal', false)
        ->whereDoesntHave('outgoingTransitions', fn ($query) => $query->where('is_active', true))
        ->pluck('code');

    expect($orphans)->toBeEmpty();
});

it('kritik geçişlerin izin ve koşul kancalarını veri olarak saklar', function (): void {
    $assignment = Transition::query()
        ->whereRelation('fromStatus', 'code', 'awaiting_assignment')
        ->whereRelation('toStatus', 'code', 'pm_assigned')
        ->firstOrFail();
    $documentsComplete = Transition::query()
        ->whereRelation('fromStatus', 'code', 'collecting_documents')
        ->whereRelation('toStatus', 'code', 'preparing_application')
        ->firstOrFail();

    expect($assignment->required_permission)->toBe('deal.assign')
        ->and($documentsComplete->required_permission)->toBe('deal.transition')
        ->and(data_get($documentsComplete->condition, 'all.0.field'))->toBe('deal.required_documents.status')
        ->and(data_get($documentsComplete->condition, 'all.0.op'))->toBe('all_in')
        ->and(data_get($documentsComplete->condition, 'all.0.value'))->toBe(['accepted', 'not_required']);
});

it('sistem yöneticisini hassas iş verisi izinlerinden ayırır', function (): void {
    $role = Role::findByName('Sistem Yöneticisi');

    expect($role->hasPermissionTo('system.users'))->toBeTrue()
        ->and($role->hasPermissionTo('system.roles'))->toBeTrue()
        ->and($role->hasPermissionTo('system.settings'))->toBeTrue()
        ->and($role->hasPermissionTo('system.templates'))->toBeTrue()
        ->and($role->hasPermissionTo('document.download'))->toBeFalse()
        ->and($role->hasPermissionTo('deal.view_all'))->toBeFalse();
});

it('pazarlamaya yalnız kendi kapsamındaki belgeleri yükleme ve indirme izni verir', function (): void {
    $role = Role::findByName('Pazarlama');

    expect($role->hasPermissionTo('document.upload'))->toBeTrue()
        ->and($role->hasPermissionTo('document.download'))->toBeTrue()
        ->and($role->hasPermissionTo('document.approve'))->toBeFalse();
});

it('dört rolün varsayılan veri kapsamını doğru kurar', function (): void {
    $scopes = DB::table('roles')->pluck('default_scope', 'name')->all();

    expect($scopes)->toMatchArray([
        'Pazarlama' => 'own',
        'Proje Yöneticisi' => 'team',
        'Şirket Yetkilisi' => 'all',
        'Sistem Yöneticisi' => 'none',
    ]);
});

it('Yeşil Sanayi çağrısının yedi evrak şablonunu kurar', function (): void {
    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
    $version = $program->versions()->where('call_period', '2026 çağrısı')->firstOrFail();
    $templates = DocTemplate::query()->where('program_version_id', $version->id)->get();

    expect($program->name)->toBe('KOSGEB Yeşil Sanayi Destek Programı')
        ->and($templates)->toHaveCount(7)
        ->and($templates->whereNotNull('condition'))->toHaveCount(2)
        ->and($templates->firstWhere('name', 'Findeks Raporu')?->validity_days)->toBe(30);
});

it('koşullu şablonları gerçek kolon alanlarıyla tanımlar', function (): void {
    $conditions = DocTemplate::query()
        ->whereNotNull('condition')
        ->get()
        ->mapWithKeys(fn (DocTemplate $template): array => [$template->name => $template->condition]);

    expect(data_get($conditions, 'Hasar Durumu Belgesi.all.0.field'))->toBe('company.city')
        ->and(data_get($conditions, 'Hasar Durumu Belgesi.all.0.value'))->toBe([
            'Adana', 'Adıyaman', 'Diyarbakır', 'Elazığ', 'Gaziantep', 'Hatay', 'Malatya', 'Kahramanmaraş', 'Şanlıurfa', 'Kilis', 'Osmaniye',
        ])
        ->and(data_get($conditions, 'Fizibilite Raporu.all.0.field'))->toBe('deal.requested_amount')
        ->and(data_get($conditions, 'Fizibilite Raporu.all.0.op'))->toBe('gt');
});

it('ilk workflow revizyonuna statü ve geçiş anlık görüntüsünü yazar', function (): void {
    $revision = WorkflowRevision::query()->where('reason', 'ilk kurulum')->firstOrFail();

    expect(WorkflowRevision::query()->count())->toBe(1)
        ->and($revision->snapshot['statuses'])->toHaveCount(18)
        ->and($revision->snapshot['transitions'])->not->toBeEmpty()
        ->and($revision->effective_from)->not->toBeNull();
});

it('yalnız semantik statü renk tokenlarını kabul eder ve hex değerini reddeder', function (): void {
    expect(Status::query()->whereNotIn('color', [
        'neutral', 'info', 'waiting', 'success', 'danger',
    ])->exists())->toBeFalse();

    expect(fn () => Status::query()->create([
        'type' => 'deal',
        'code' => 'invalid_hex_tone',
        'label' => 'Geçersiz renk',
        'color' => '#ff0000',
        'sort_order' => 99,
    ]))->toThrow(QueryException::class);
});
