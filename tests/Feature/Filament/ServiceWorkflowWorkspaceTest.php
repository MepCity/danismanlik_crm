<?php

declare(strict_types=1);

use App\Domain\Program\Actions\SaveServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflowStep;
use App\Filament\Resources\ServiceWorkflows\Pages\CreateServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\Pages\EditServiceWorkflow;
use App\Filament\Resources\ServiceWorkflows\Pages\ListServiceWorkflows;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

function workflowUser(string $role, string $slug): User
{
    $user = User::factory()->create(['email' => $slug.'@example.invalid']);
    $user->assignRole($role);

    return $user;
}

/** @return TestResponse<Response> */
function workflowGet(User $user, string $uri): TestResponse
{
    Auth::login($user);

    return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, 'GET')));
}

/** @param list<array{type: string, title: string, guidance: string, attention_note?: string|null}> $steps */
function makeWorkflow(User $actor, string $name, array $steps, bool $active = true): ServiceWorkflow
{
    return app(SaveServiceWorkflow::class)->execute(null, [
        'name' => $name,
        'description' => 'Kurgusal akış açıklaması',
        'is_active' => $active,
        'steps' => $steps,
    ], $actor);
}

it('yetkili kullanıcıya iş akışı kartlarını cubicl çalışma alanında gösterir', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-cards-admin');
    Auth::login($admin);

    Livewire::test(ListServiceWorkflows::class)
        ->assertSee('İş Akışı Nedir?')
        ->assertSee('KOSGEB başvuru rehberi')
        ->assertSeeHtml('data-testid="cubicl-workflow-page"')
        ->assertSeeHtml('data-testid="cubicl-workflow-cards"')
        ->assertSeeHtml('data-testid="cubicl-workflow-card"')
        ->assertSeeHtml('data-testid="cubicl-workflow-inline-create"')
        ->assertSeeHtml('data-testid="cubicl-workflow-inline-submit"')
        ->assertSeeHtml('data-testid="workflow-permissions-link"')
        ->assertDontSeeHtml('data-testid="cubicl-workflow-table"');
});

it('yetkisiz kullanıcının liste ve doğrudan url erişimini 403 ile reddeder', function (): void {
    $pm = workflowUser('Proje Yöneticisi', 'wf-unauthorized');

    expect(workflowGet($pm, '/operasyon/service-workflows')->status())->toBe(403)
        ->and(workflowGet($pm, '/operasyon/service-workflows/olustur')->status())->toBe(403);
});

it('sonuç kümesi boşken gerçek boş veri seti görünümünü verir', function (): void {
    // Workflows and steps are delete-guarded at the database level, so an empty
    // result set is produced through a real query that matches nothing.
    $admin = workflowUser('Sistem Yöneticisi', 'wf-empty');
    Auth::login($admin);

    Livewire::test(ListServiceWorkflows::class)
        ->set('workflowSearch', 'OLMAYAN_BIR_IS_AKISI_ARAMASI')
        ->assertSeeHtml('data-testid="cubicl-workflow-empty"')
        ->assertSee('Aramanızla eşleşen iş akışı bulunamadı.')
        ->assertDontSeeHtml('data-testid="cubicl-workflow-card"')
        ->assertDontSee('KOSGEB başvuru rehberi');

    expect(ServiceWorkflowStep::query()->count())->toBeGreaterThan(0);
});

it('dolu akış kartında etkin aşamaları sort_order sırasıyla listeler', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-ordered');
    $workflow = makeWorkflow($admin, 'Kurgusal Sıra Akışı', [
        ['type' => 'action', 'title' => 'Birinci aşama', 'guidance' => 'İlk yapılacak.'],
        ['type' => 'waiting', 'title' => 'İkinci aşama', 'guidance' => 'Bekleyin.'],
        ['type' => 'decision', 'title' => 'Üçüncü aşama', 'guidance' => 'Karar verin.'],
    ]);
    Auth::login($admin);

    $html = Livewire::test(ListServiceWorkflows::class)->html();

    $first = strpos($html, 'Birinci aşama');
    $second = strpos($html, 'İkinci aşama');
    $third = strpos($html, 'Üçüncü aşama');

    expect($first)->toBeLessThan($second)
        ->and($second)->toBeLessThan($third)
        ->and($html)->toContain('data-step-type="action"')
        ->and($html)->toContain('data-step-type="waiting"')
        ->and($html)->toContain('data-step-type="decision"')
        ->and($workflow->steps()->where('is_active', true)->count())->toBe(3);
});

it('kartlarda yalnız etkin aşamaları gösterir', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-active-only');
    $workflow = makeWorkflow($admin, 'Kurgusal Pasif Aşama Akışı', [
        ['type' => 'action', 'title' => 'Kalan aşama', 'guidance' => 'Kalır.'],
        ['type' => 'action', 'title' => 'Kaldirilan asama', 'guidance' => 'Çıkarılacak.'],
    ]);

    $kept = $workflow->steps()->where('title', 'Kalan aşama')->firstOrFail();

    app(SaveServiceWorkflow::class)->execute($workflow, [
        'name' => $workflow->name,
        'description' => $workflow->description,
        'is_active' => true,
        'steps' => [['id' => $kept->id, 'type' => 'action', 'title' => 'Kalan aşama', 'guidance' => 'Kalır.']],
    ], $admin);

    Auth::login($admin);

    Livewire::test(ListServiceWorkflows::class)
        ->assertSee('Kalan aşama')
        ->assertDontSee('Kaldirilan asama');
});

it('inline ad alanı boşken kaydı reddeder ve türkçe hata gösterir', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-inline-empty');
    Auth::login($admin);

    $before = ServiceWorkflow::query()->count();

    Livewire::test(ListServiceWorkflows::class)
        ->set('newWorkflowName', '   ')
        ->call('startWorkflow')
        ->assertHasErrors('newWorkflowName')
        ->assertNoRedirect();

    expect(ServiceWorkflow::query()->count())->toBe($before);
});

it('inline adı oluşturma çalışma alanına taşır ve veritabanına kayıt yazmaz', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-inline-carry');
    Auth::login($admin);

    $before = ServiceWorkflow::query()->count();

    Livewire::test(ListServiceWorkflows::class)
        ->set('newWorkflowName', 'Kurgusal Aktarım Akışı')
        ->call('startWorkflow')
        ->assertHasNoErrors()
        ->assertRedirect(ServiceWorkflowResource::getUrl('create', ['name' => 'Kurgusal Aktarım Akışı']));

    expect(ServiceWorkflow::query()->count())->toBe($before)
        ->and(ServiceWorkflow::query()->where('name', 'Kurgusal Aktarım Akışı')->exists())->toBeFalse();
});

it('oluşturma formunu taşınan adla doldurur', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-prefill');
    Auth::login($admin);

    $response = workflowGet($admin, '/operasyon/service-workflows/olustur?name=Kurgusal+Onceden+Dolu');

    $response->assertOk();
    expect($response->getContent())->toContain('Kurgusal Onceden Dolu');
});

it('aşamasız kaydı sunucu tarafında reddeder', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-no-steps');
    Auth::login($admin);

    $before = ServiceWorkflow::query()->count();

    Livewire::test(CreateServiceWorkflow::class)
        ->fillForm(['name' => 'Kurgusal Aşamasız', 'is_active' => true, 'steps' => []])
        ->call('create')
        ->assertHasFormErrors();

    expect(ServiceWorkflow::query()->count())->toBe($before);
});

it('geçerli akışı en az bir aşamayla oluşturur', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-create-ok');
    Auth::login($admin);

    Livewire::test(CreateServiceWorkflow::class)
        ->fillForm([
            'name' => 'Kurgusal Yeni Akış',
            'is_active' => true,
            'steps' => [
                ['type' => 'action', 'title' => 'Evrak topla', 'guidance' => 'Belgeleri toplayın.', 'attention_note' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $workflow = ServiceWorkflow::query()->where('name', 'Kurgusal Yeni Akış')->firstOrFail();

    expect($workflow->steps()->where('is_active', true)->count())->toBe(1)
        ->and($workflow->steps()->first()->type)->toBe('action');
});

it('düzenlemede sırayı günceller ve çıkarılan aşamayı silmeden pasifleştirir', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-edit-order');
    $workflow = makeWorkflow($admin, 'Kurgusal Düzenleme Akışı', [
        ['type' => 'action', 'title' => 'Aşama A', 'guidance' => 'A yapılır.'],
        ['type' => 'waiting', 'title' => 'Aşama B', 'guidance' => 'B beklenir.'],
    ]);

    $stepA = $workflow->steps()->where('title', 'Aşama A')->firstOrFail();
    $stepB = $workflow->steps()->where('title', 'Aşama B')->firstOrFail();

    Auth::login($admin);

    Livewire::test(EditServiceWorkflow::class, ['record' => $workflow->getKey()])
        ->fillForm([
            'name' => 'Kurgusal Düzenleme Akışı',
            'is_active' => true,
            'steps' => [
                ['id' => $stepB->id, 'type' => 'waiting', 'title' => 'Aşama B', 'guidance' => 'B beklenir.', 'attention_note' => null],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(ServiceWorkflowStep::query()->whereKey($stepA->id)->exists())->toBeTrue()
        ->and(ServiceWorkflowStep::query()->whereKey($stepA->id)->value('is_active'))->toBeFalse()
        ->and(ServiceWorkflowStep::query()->whereKey($stepB->id)->value('sort_order'))->toBe(0);
});

it('başka akışa ait aşama kimliğini reddeder', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-foreign-step');
    $first = makeWorkflow($admin, 'Kurgusal Birinci Akış', [
        ['type' => 'action', 'title' => 'Birinciye ait', 'guidance' => 'Sadece birincide.'],
    ]);
    $second = makeWorkflow($admin, 'Kurgusal İkinci Akış', [
        ['type' => 'action', 'title' => 'İkinciye ait', 'guidance' => 'Sadece ikincide.'],
    ]);

    $foreignStep = $first->steps()->firstOrFail();

    expect(fn () => app(SaveServiceWorkflow::class)->execute($second, [
        'name' => $second->name,
        'is_active' => true,
        'steps' => [['id' => $foreignStep->id, 'type' => 'action', 'title' => 'Çalınan', 'guidance' => 'Olmaz.']],
    ], $admin))->toThrow(ValidationException::class);

    expect(ServiceWorkflowStep::query()->whereKey($foreignStep->id)->value('service_workflow_id'))->toBe($first->id);
});

it('yeni aşama eylemi formu tek boş taslak aşamayla açar ve veritabanına yazmaz', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-draft-step');
    $workflow = makeWorkflow($admin, 'Kurgusal Dört Aşamalı Akış', [
        ['type' => 'action', 'title' => 'Aşama 1', 'guidance' => 'Bir.'],
        ['type' => 'waiting', 'title' => 'Aşama 2', 'guidance' => 'İki.'],
        ['type' => 'decision', 'title' => 'Aşama 3', 'guidance' => 'Üç.'],
        ['type' => 'action', 'title' => 'Aşama 4', 'guidance' => 'Dört.'],
    ]);

    expect($workflow->steps()->where('is_active', true)->count())->toBe(4);

    Auth::login($admin);

    // The list screen links to the edit screen with the one-shot draft flag.
    $listHtml = Livewire::test(ListServiceWorkflows::class)->html();
    expect($listHtml)->toContain('yeniAsama=1');

    /** @phpstan-ignore-next-line argument.templateType */
    $component = Livewire::withQueryParams(['yeniAsama' => '1'])->test(EditServiceWorkflow::class, ['record' => $workflow->getKey()]);

    $steps = $component->get('data.steps');

    expect($steps)->toHaveCount(5);

    $draft = array_values($steps)[4];

    expect($draft['id'])->toBeNull()
        ->and($draft['title'])->toBe('')
        ->and($draft['guidance'])->toBe('')
        ->and($draft['type'])->toBe('action')
        // nothing persisted yet
        ->and($workflow->steps()->where('is_active', true)->count())->toBe(4)
        ->and(ServiceWorkflowStep::query()->where('service_workflow_id', $workflow->id)->count())->toBe(4);

    // A further hydration/render must not add a sixth item.
    $component->call('$refresh');

    expect($component->get('data.steps'))->toHaveCount(5)
        ->and(ServiceWorkflowStep::query()->where('service_workflow_id', $workflow->id)->count())->toBe(4);
});

it('normal düzenle bağlantısı taslak aşama eklemez', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-plain-edit');
    $workflow = makeWorkflow($admin, 'Kurgusal Düz Düzenleme', [
        ['type' => 'action', 'title' => 'Tek aşama', 'guidance' => 'Tek.'],
    ]);
    Auth::login($admin);

    $component = Livewire::test(EditServiceWorkflow::class, ['record' => $workflow->getKey()]);

    expect($component->get('data.steps'))->toHaveCount(1)
        ->and(ServiceWorkflowStep::query()->where('service_workflow_id', $workflow->id)->count())->toBe(1);
});

it('taslak aşama kaydedilmeden çıkıldığında mevcut aşamaları değiştirmez', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-draft-abandon');
    $workflow = makeWorkflow($admin, 'Kurgusal Vazgeçme Akışı', [
        ['type' => 'action', 'title' => 'Korunan aşama', 'guidance' => 'Kalır.'],
    ]);
    $before = $workflow->steps()->where('is_active', true)->get()
        ->map(fn ($s): array => ['id' => $s->id, 'title' => $s->title, 'sort_order' => $s->sort_order])->all();

    Auth::login($admin);

    /** @phpstan-ignore-next-line argument.templateType */
    Livewire::withQueryParams(['yeniAsama' => '1'])->test(EditServiceWorkflow::class, ['record' => $workflow->getKey()]);

    $after = $workflow->fresh()->steps()->where('is_active', true)->get()
        ->map(fn ($s): array => ['id' => $s->id, 'title' => $s->title, 'sort_order' => $s->sort_order])->all();

    expect($after)->toBe($before);
});

it('inline yardım metnini görünür satır yerine erişilebilir açıklama olarak tutar', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-hint-sr');
    Auth::login($admin);

    $html = Livewire::test(ListServiceWorkflows::class)->html();

    expect($html)->toContain('aria-describedby="cubicl-workflow-new-hint"')
        ->and($html)->toContain(__('management.workflow_setup.inline.hint'))
        ->and($html)->toMatch('/id="cubicl-workflow-new-hint"[^>]*class="[^"]*fi-sr-only/');
});

it('kullanıcı izinleri erişimi olmayana bağlantı yerine rozet gösterir', function (): void {
    $officer = workflowUser('Şirket Yetkilisi', 'wf-permission-badge');
    Auth::login($officer);

    Livewire::test(ListServiceWorkflows::class)
        ->assertSeeHtml('data-testid="workflow-permissions-badge"')
        ->assertDontSeeHtml('data-testid="workflow-permissions-link"');
});

it('arama inline oluşturma alanını değiştirmeden listeyi daraltır', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-search');
    makeWorkflow($admin, 'Kurgusal Arama Hedefi', [
        ['type' => 'action', 'title' => 'Hedef aşama', 'guidance' => 'Bulunur.'],
    ]);
    Auth::login($admin);

    Livewire::test(ListServiceWorkflows::class)
        ->set('workflowSearch', 'Arama Hedefi')
        ->assertSee('Kurgusal Arama Hedefi')
        ->assertDontSee('KOSGEB başvuru rehberi')
        ->assertSet('newWorkflowName', '')
        ->assertSeeHtml('data-testid="cubicl-workflow-inline-create"');
});

it('erişilebilir ad ve klavye odağı sözleşmesini korur', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-a11y');
    Auth::login($admin);

    $html = Livewire::test(ListServiceWorkflows::class)->html();

    expect($html)->toContain('for="cubicl-workflow-new-name"')
        ->and($html)->toContain('id="cubicl-workflow-new-name"')
        ->and($html)->toContain('aria-label="'.__('management.workflow_setup.search.label').'"')
        ->and($html)->toContain('aria-describedby="cubicl-workflow-new-hint"')
        ->and($html)->toContain('aria-label="'.__('management.workflow_setup.card.steps_label', ['name' => 'KOSGEB başvuru rehberi']).'"');

    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    expect($theme)->toContain('.cubicl-workflow__inline-field:focus-within');
});

it('akış kartı ve aşama geometrisini açık ve koyu temada tokenlarla kurar', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->toContain('--crm-workflow-stage-width: 200px;')
        ->and($tokens)->toContain('--crm-workflow-stage-min-height: 80.5px;')
        ->and($tokens)->toContain('--crm-workflow-stage-radius: 5px;')
        ->and($tokens)->toContain('--crm-workflow-stage-padding: 10px;')
        ->and($tokens)->toContain('--crm-workflow-pill-radius: 20px;')
        ->and($tokens)->toContain('--crm-nav-mark-border:')
        // vertical rhythm contract measured against the live reference
        ->and($tokens)->toContain('--crm-workflow-intro-min-height: 145px;')
        ->and($tokens)->toContain('--crm-workflow-section-gap: 32.8px;')
        ->and($tokens)->toContain('--crm-workflow-list-gap: 8.8px;')
        ->and($tokens)->toContain('--crm-workflow-card-gap: 20px;')
        ->and($tokens)->toContain('--crm-workflow-stage-inset: 30px;')
        ->and($theme)->toContain('min-height: var(--crm-workflow-intro-min-height);')
        ->and($theme)->toContain('gap: var(--crm-workflow-section-gap);')
        ->and($theme)->toContain('padding-inline-start: var(--crm-workflow-stage-inset);')
        ->and($theme)->toContain('gap: var(--crm-workflow-card-gap);')
        // horizontal stage scrolling must survive on narrow screens
        ->and($theme)->toContain('overflow-x: auto;')
        ->and($theme)->toContain('width: var(--crm-workflow-stage-width);')
        ->and($theme)->toContain('border-radius: var(--crm-workflow-stage-radius);')
        ->and($theme)->toContain('border-color: var(--crm-nav-mark-border);')
        // the retired Filament table shell is gone
        ->and($theme)->not->toContain('.cubicl-workflow__table')
        // dark theme redefines the stage-type palette independently
        ->and(substr_count($tokens, '--crm-workflow-stage-action'))->toBeGreaterThan(1);

    $darkBlock = substr($tokens, (int) strpos($tokens, '.dark {'));
    expect($darkBlock)->toContain('--crm-workflow-stage-action:')
        ->and($darkBlock)->toContain('--crm-nav-mark-border:');
});

it('aşama tipini yalnız renkle değil ikon ve etiketle kodlar', function (): void {
    $admin = workflowUser('Sistem Yöneticisi', 'wf-step-coding');
    makeWorkflow($admin, 'Kurgusal Tip Akışı', [
        ['type' => 'waiting', 'title' => 'Bekleme adımı', 'guidance' => 'Beklenir.'],
    ]);
    Auth::login($admin);

    $html = Livewire::test(ListServiceWorkflows::class)->html();

    // Type is carried by a data attribute, a visible Turkish label and an icon
    // glyph — not by colour alone.
    expect($html)->toContain('cubicl-workflow__stage-type--waiting')
        ->and($html)->toContain('data-step-type="waiting"')
        ->and($html)->toContain(__('management.workflow_step_types.waiting'))
        ->and($html)->toMatch('/cubicl-workflow__stage-type cubicl-workflow__stage-type--waiting[^>]*>\s*<svg/');
});
