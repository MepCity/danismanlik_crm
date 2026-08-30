<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Support\CompanyWorkspaceSummary;
use App\Livewire\CollaborationComments;
use App\Livewire\CollaborationTimeline;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

/** @return array{owner: User, outsider: User, company: Company, contact: Contact} */
function customerWorkspaceFixture(): array
{
    $owner = User::factory()->create(['name' => 'Kurgusal Deniz Uzman', 'email' => 'deniz-musteri@example.invalid']);
    $owner->assignRole('Pazarlama');
    $outsider = User::factory()->create(['name' => 'Kurgusal Kenan Uzman', 'email' => 'kenan-musteri@example.invalid']);
    $outsider->assignRole('Pazarlama');

    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Meridyen Enerji AŞ',
        'industry' => 'energy',
        'city' => 'Ankara',
        'owner_user_id' => $owner->id,
        'is_active' => true,
    ]);

    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal Selin Yetkili',
        'title' => 'Genel Müdür',
        'phone' => '05001112233',
        'email' => 'selin@kurgusal.invalid',
        'is_primary' => true,
        'is_active' => true,
        'data_source' => 'other',
        'created_by' => $owner->id,
    ]);

    return ['owner' => $owner, 'outsider' => $outsider, 'company' => $company, 'contact' => $contact];
}

it('yetkili kullanıcı müşteri detayını cubicl yerleşimiyle açar', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertSet('activeTab', 'activities')
        ->assertSeeHtml('data-testid="customer-identity"')
        ->assertSeeHtml('data-testid="customer-note"')
        ->assertSeeHtml('data-testid="customer-tabs"')
        ->assertSeeHtml('data-testid="customer-summary"')
        ->assertSee($fixture['company']->legal_name)
        ->assertSee($fixture['contact']->full_name)
        ->assertSee('Genel Müdür')
        ->assertSee('05001112233');
});

it('kapsam dışındaki kullanıcı müşteri detayını göremez', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['outsider']);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertStatus(403);
});

it('işlemler menüsünde yalnız kapsam içi eylemler bulunur', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $html = Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])->html();

    expect($html)->toContain('data-testid="customer-actions-trigger"')
        ->and($html)->toContain('aria-haspopup="menu"')
        ->and($html)->toContain('aria-controls="customer-actions-menu"')
        ->and($html)->toContain('Firma bilgilerini düzenle')
        // deliberately out of scope
        ->and($html)->not->toContain('Birleştir')
        ->and($html)->not->toContain('Müşteri portalı')
        ->and($html)->not->toContain('Cari hesap')
        ->and($html)->not->toContain('Teklif');
});

it('boş not gönderimini reddeder', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $before = Comment::query()->count();

    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])
        ->set('body', '   ')
        ->call('save')
        ->assertHasErrors('body');

    expect(Comment::query()->count())->toBe($before);
});

it('notu CommentService üzerinden oluşturur, composer temizlenir ve akışta görünür', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])
        ->set('body', 'Kurgusal ilk firma notu')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertDispatched('comments-updated');

    $comment = Comment::query()->where('company_id', $fixture['company']->id)->sole();

    expect($comment->body)->toBe('Kurgusal ilk firma notu')
        ->and($comment->user_id)->toBe($fixture['owner']->id);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertSee('Kurgusal ilk firma notu');
});

it('yeni not aynı sayfadaki zaman çizgisinde tam yenileme olmadan görünür', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    // timeline is mounted first and is empty
    $timeline = Livewire::test(CollaborationTimeline::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'filter' => 'comment',
        'embedded' => true,
    ])->assertDontSee('Kurgusal canlı not');

    // the sibling composer posts a note on the same page
    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])
        ->set('body', 'Kurgusal canlı not')
        ->call('save')
        ->assertSet('body', '')
        ->assertDispatched('comments-updated');

    // the very same timeline instance repaints on the event, without remounting
    $timeline->dispatch('comments-updated', subjectType: 'company', subjectId: $fixture['company']->id)
        ->assertSee('Kurgusal canlı not');
});

it('başka müşterinin notu mevcut zaman çizgisini yenilemez', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $other = Company::query()->create([
        'legal_name' => 'Kurgusal Diğer Firma AŞ',
        'industry' => 'services',
        'owner_user_id' => $fixture['owner']->id,
        'is_active' => true,
    ]);

    $timeline = Livewire::test(CollaborationTimeline::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'filter' => 'comment',
        'embedded' => true,
    ])->set('page', 3);

    // an event for a different subject must be ignored
    $timeline->dispatch('comments-updated', subjectType: 'company', subjectId: $other->id)
        ->assertSet('page', 3);

    // the matching subject does reset the view
    $timeline->dispatch('comments-updated', subjectType: 'company', subjectId: $fixture['company']->id)
        ->assertSet('page', 1);
});

it('inline mention seçimini güvenli biçimde ekler ve zorlanan geçersiz kimliği reddeder', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $mentioned = User::factory()->create(['name' => 'Kurgusal Bahsedilen Kişi', 'email' => 'bahsedilen@example.invalid']);
    $mentioned->assignRole('Pazarlama');

    $component = Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])
        ->set('body', 'Merhaba @Bahsed')
        ->call('insertMention', $mentioned->id);

    expect($component->get('body'))->toContain('@[Kurgusal Bahsedilen Kişi](user:'.$mentioned->id.')');

    $component->call('save')->assertHasNoErrors();

    // the reader never sees the internal mention format
    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertSeeHtml('<span class="comment-mention">@Kurgusal Bahsedilen Kişi</span>')
        ->assertDontSee('user:'.$mentioned->id);

    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])->call('insertMention', 999999)->assertStatus(404);
});

it('yetkisiz kullanıcı not oluşturamaz', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['outsider']);

    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])->assertStatus(403);

    expect(Comment::query()->where('company_id', $fixture['company']->id)->count())->toBe(0);
});

it('görev modalından görev oluşturur ve görevler sekmesine geçer', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->callAction('create_task', [
            'title' => 'Kurgusal takip görevi',
            'assigned_to' => $fixture['owner']->id,
            'description' => 'Kurgusal açıklama',
        ])
        ->assertHasNoActionErrors()
        ->assertSet('activeTab', 'tasks');

    $task = Task::query()->where('company_id', $fixture['company']->id)->sole();

    expect($task->title)->toBe('Kurgusal takip görevi')
        ->and($task->assigned_to)->toBe($fixture['owner']->id)
        ->and($task->completed_at)->toBeNull();
});

it('yetkisiz kullanıcı görev oluşturma eylemini görmez', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['outsider']);

    expect(Task::query()->where('company_id', $fixture['company']->id)->count())->toBe(0);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertStatus(403);
});

it('dosyalar sekmesi ve özet sayaçları yalnız kapsam içi veriden gelir', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->call('setActiveTab', 'files')
        ->assertSee('Bu firmaya bağlı dosya yok.');

    $summary = CompanyWorkspaceSummary::for($fixture['company'], $fixture['owner']);

    expect($summary->activeDeals)->toBe(0)
        ->and($summary->openLeads)->toBe(0)
        ->and($summary->pendingDocuments)->toBe(0)
        ->and($summary->openTasks)->toBe(0)
        ->and($summary->overdueTasks)->toBe(0)
        ->and($summary->ownerName)->toBe($fixture['owner']->name);
});

it('müşteri detayı sorgu sayısını kayıt sayısıyla doğrusal büyütmez', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $measure = function () use ($fixture): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])->html();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $baseline = $measure();

    for ($i = 0; $i < 6; $i++) {
        Contact::query()->create([
            'company_id' => $fixture['company']->id,
            'full_name' => 'Kurgusal Ek Kişi '.$i,
            'is_primary' => false,
            'is_active' => true,
            'data_source' => 'other',
            'created_by' => $fixture['owner']->id,
        ]);
    }

    expect($measure())->toBeLessThanOrEqual($baseline + 2);
});

it('açık ve koyu tema için müşteri ekranı token sözleşmesini korur', function (): void {
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    $view = file_get_contents(resource_path('views/filament/resources/companies/view-company.blade.php'));

    expect($theme)->not->toBeFalse()
        ->and($view)->not->toBeFalse()
        ->and($theme)->toContain('.customer-identity {')
        ->and($theme)->toContain('.comment-composer--compact')
        ->and($theme)->toContain('.customer-tab--active')
        ->and($theme)->toContain('.customer-columns')
        ->and($theme)->toContain('.mention-suggestions')
        ->and($theme)->toContain('background: var(--crm-surface-panel);')
        ->and($theme)->toContain('border: 1px solid var(--crm-border);')
        // no raw colours and no inline styles in the customer screen
        ->and($view)->not->toMatch('/style="[^"]*(#|rgb|hsl)/i')
        ->and($view)->not->toContain('style="');
});

it('karma kapsamda yalnız görülebilir fırsat ve dosyaları gösterir', function (): void {
    $fixture = customerWorkspaceFixture();
    $owner = $fixture['owner'];
    $company = $fixture['company'];

    // a second marketer owns the out-of-scope records on the SAME company
    $stranger = User::factory()->create(['name' => 'Kurgusal Yabanci', 'email' => 'yabanci-scope@example.invalid']);
    $stranger->assignRole('Pazarlama');

    $leadStatus = Status::query()->where('type', 'lead')->where('is_initial', true)->firstOrFail();
    $dealStatus = Status::query()->where('type', 'deal')->where('is_initial', true)->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();

    $visibleLead = Lead::query()->create([
        'company_id' => $company->id, 'owner_user_id' => $owner->id, 'status_id' => $leadStatus->id,
        'interested_program_version_id' => $version->id, 'source' => 'other',
    ]);
    $hiddenLead = Lead::query()->create([
        'company_id' => $company->id, 'owner_user_id' => $stranger->id, 'status_id' => $leadStatus->id,
        'interested_program_version_id' => $version->id, 'source' => 'other',
    ]);

    $visibleDeal = Deal::query()->create([
        'company_id' => $company->id, 'program_version_id' => $version->id, 'status_id' => $dealStatus->id,
        'reference_no' => 'KAPSAMICI-DOSYA', 'opened_by_user_id' => $owner->id, 'status_changed_at' => now(),
    ]);
    $hiddenDeal = Deal::query()->create([
        'company_id' => $company->id, 'program_version_id' => $version->id, 'status_id' => $dealStatus->id,
        'reference_no' => 'KAPSAMDISI-DOSYA', 'opened_by_user_id' => $stranger->id, 'status_changed_at' => now(),
    ]);

    Auth::login($owner);

    $summary = CompanyWorkspaceSummary::for($company, $owner);

    expect($summary->leads->pluck('id')->all())->toBe([$visibleLead->id])
        ->and($summary->deals->pluck('id')->all())->toBe([$visibleDeal->id])
        ->and($summary->openLeads)->toBe(1)
        ->and($summary->activeDeals)->toBe(1);

    $filesHtml = Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->call('setActiveTab', 'files')
        ->html();

    expect($filesHtml)->toContain('KAPSAMICI-DOSYA')
        ->and($filesHtml)->not->toContain('KAPSAMDISI-DOSYA');

    $leadHtml = Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->call('setActiveTab', 'opportunities')
        ->html();

    expect($leadHtml)->not->toContain('Kurgusal Yabanci');

    // both rows still exist in the database; only the view is scoped
    expect(Lead::query()->where('company_id', $company->id)->count())->toBe(2)
        ->and(Deal::query()->where('company_id', $company->id)->count())->toBe(2)
        ->and($hiddenLead->exists)->toBeTrue()
        ->and($hiddenDeal->exists)->toBeTrue();
});

it('görev kapsamı mevcut ScopedQuery firma kuralını izler', function (): void {
    // Documented domain rule: ScopedQuery::tasks() grants visibility through the
    // company relation, so a viewer who may see the company sees its tasks.
    $fixture = customerWorkspaceFixture();
    $owner = $fixture['owner'];

    $stranger = User::factory()->create(['name' => 'Kurgusal Gorev Sahibi', 'email' => 'gorev-sahibi@example.invalid']);
    $stranger->assignRole('Pazarlama');

    Task::query()->create([
        'company_id' => $fixture['company']->id, 'assigned_to' => $stranger->id, 'created_by' => $stranger->id,
        'title' => 'FIRMA UZERINDEN GORUNUR GOREV',
    ]);

    Auth::login($owner);
    $summary = CompanyWorkspaceSummary::for($fixture['company'], $owner);

    expect($summary->tasks)->toHaveCount(1);

    // a company the viewer cannot see keeps its tasks hidden
    $foreignCompany = Company::query()->create([
        'legal_name' => 'Kurgusal Erisilemez AS', 'industry' => 'services',
        'owner_user_id' => $stranger->id, 'is_active' => true,
    ]);
    Task::query()->create([
        'company_id' => $foreignCompany->id, 'assigned_to' => $stranger->id, 'created_by' => $stranger->id,
        'title' => 'ERISILEMEZ GOREV',
    ]);

    expect(CompanyWorkspaceSummary::for($fixture['company'], $owner)->tasks
        ->pluck('title')->all())->not->toContain('ERISILEMEZ GOREV');
});

it('görev atama listesi yalnız firmayı görebilen kullanıcıları içerir', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $outsider = $fixture['outsider'];

    $component = Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->mountAction('create_task');

    $page = $component->instance();
    expect($page instanceof ViewCompany)->toBeTrue();
    /** @var ViewCompany $page */
    $select = $page->getSchemaComponent('mountedActionSchema0.assigned_to');
    expect($select instanceof Select)->toBeTrue();
    /** @var Select $select */
    $options = $select->getOptions();

    expect($options)->toHaveKey($fixture['owner']->id)
        ->and($options)->not->toHaveKey($outsider->id);

    // forcing a user who cannot see the company is rejected server-side
    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->callAction('create_task', [
            'title' => 'Kurgusal zorlanan atama',
            'assigned_to' => $outsider->id,
        ])
        ->assertHasActionErrors(['assigned_to']);

    expect(Task::query()->where('title', 'Kurgusal zorlanan atama')->count())->toBe(0);
});

it('zaman çizgisi gün gruplaması ve sarı not yüzeyi ile render edilir', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])->set('body', 'Kurgusal gün grubu notu')->call('save');

    $html = Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])->html();

    expect($html)->toContain('data-testid="timeline-day"')
        ->and($html)->toContain('Bugün')
        ->and($html)->toContain('data-testid="timeline-note"')
        ->and($html)->toContain(__('collaboration.activity.note_added'))
        ->and($html)->toContain('Kurgusal gün grubu notu')
        // no raw wrapper or internal mention format
        ->and($html)->not->toContain('yorum: &quot;');

    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    expect($theme)->toContain('.timeline-day__label')
        ->and($theme)->toContain('.timeline-entry__note')
        ->and($theme)->toContain('border-inline-start: 1px dashed');
});

it('eylem modal sarmalayıcısını sayfa animasyonunun dışında tutar', function (): void {
    // A finished "both" animation leaves transform on the wrapper, which turns it
    // into a containing block and drags every fixed modal out of the viewport.
    $css = (string) file_get_contents(base_path('resources/css/filament/operations/theme.css'));

    expect($css)->toContain(".fi-page > div:not([x-data^='filamentActionModals'])")
        ->and($css)->not->toContain("\n.fi-page > div,\n");
});

it('not gönder düğmesini istemci tarafında da canlı tutar', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    $html = Livewire::test(CollaborationComments::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'compact' => true,
    ])->html();

    // wire:model is deferred, so without the Alpine binding the button would stay
    // disabled and the note could never be sent from the browser.
    expect($html)->toContain('x-bind:disabled="draft.trim()')
        ->and($html)->toContain('draft = $event.target.value')
        ->and($html)->toContain('role="combobox"')
        ->and($html)->toContain('aria-activedescendant');
});

/* ---------------------------------------------------------------------------
 | Timeline variant isolation
 |--------------------------------------------------------------------------*/

it('müşteri varyantı gün etiketi ve sarı not yüzeyi ile render edilir', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Comment::query()->create([
        'company_id' => $fixture['company']->id,
        'user_id' => $fixture['owner']->id,
        'body' => 'Kurgusal varyant notu',
    ]);

    $html = Livewire::test(CollaborationTimeline::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'filter' => 'comment',
        'embedded' => true,
        'variant' => 'customer',
    ])->html();

    expect($html)->toContain('timeline-panel--customer')
        ->and($html)->toContain('data-testid="timeline-day-label"')
        ->and($html)->toContain('data-testid="timeline-note"')
        ->and($html)->toContain('Bugün')
        ->and($html)->not->toContain('class="timeline-list"');
});

it('varsayılan varyant müşteri sınıflarını hiçbir öznede göstermez', function (): void {
    $fixture = customerWorkspaceFixture();
    $owner = $fixture['owner'];
    Auth::login($owner);

    $dealStatus = Status::query()->where('type', 'deal')->where('is_initial', true)->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $fixture['company']->id,
        'program_version_id' => $version->id,
        'status_id' => $dealStatus->id,
        'reference_no' => 'VARYANT-DOSYA',
        'opened_by_user_id' => $owner->id,
        'status_changed_at' => now(),
    ]);

    Comment::query()->create(['deal_id' => $deal->id, 'user_id' => $owner->id, 'body' => 'Kurgusal dosya notu']);

    foreach ([['deal', $deal->id], ['company', $fixture['company']->id]] as [$subjectType, $subjectId]) {
        $html = Livewire::test(CollaborationTimeline::class, [
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
        ])->html();

        expect($html)->toContain('class="timeline-list"')
            ->and($html)->not->toContain('timeline-panel--customer')
            ->and($html)->not->toContain('timeline-day__label')
            ->and($html)->not->toContain('data-testid="timeline-note"');
    }
});

it('bilinmeyen timeline varyantını 422 ile reddeder', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    Livewire::test(CollaborationTimeline::class, [
        'subjectType' => 'company',
        'subjectId' => $fixture['company']->id,
        'variant' => 'cubicl',
    ])->assertStatus(422);
});

it('müşteri gün gruplu görünüm CSS kuralları yalnız müşteri panelinde geçerlidir', function (): void {
    $css = (string) file_get_contents(base_path('resources/css/filament/operations/theme.css'));

    foreach (preg_split('/\r?\n/', $css) ?: [] as $line) {
        if (preg_match('/^\.timeline-(day|entry)/', $line) === 1) {
            throw new RuntimeException('Kapsamsız müşteri timeline seçicisi: '.$line);
        }
    }

    expect($css)->toContain('.timeline-panel--customer .timeline-day__label')
        ->and($css)->toContain('.timeline-panel--customer .timeline-entry__note');
});

/* ---------------------------------------------------------------------------
 | Policy + scope must both hold
 |--------------------------------------------------------------------------*/

/**
 * A user whose data scope reaches everything, holding only the given permissions.
 *
 * @param  list<string>  $permissions
 */
function customerWorkspaceUser(string $name, string $email, array $permissions): User
{
    $user = User::factory()->create(['name' => $name, 'email' => $email, 'is_active' => true, 'data_scope' => 'all']);

    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }

    return $user->fresh() ?? $user;
}

it('görev atama listesi veri kapsamı yeten ama company.manage izni olmayan kullanıcıyı dışlar', function (): void {
    $fixture = customerWorkspaceFixture();
    Auth::login($fixture['owner']);

    // data scope "all" reaches the company row, but the company permission is missing
    $scopeOnly = customerWorkspaceUser('Kurgusal Kapsamli Izinsiz', 'kapsam-izinsiz@example.invalid', ['task.manage']);
    expect(app(ScopedQuery::class)->contains($scopeOnly, $fixture['company'], 'view'))->toBeTrue()
        ->and(Gate::forUser($scopeOnly)->allows('view', $fixture['company']))->toBeFalse();

    $component = Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->mountAction('create_task');

    $page = $component->instance();
    expect($page instanceof ViewCompany)->toBeTrue();
    /** @var ViewCompany $page */
    $select = $page->getSchemaComponent('mountedActionSchema0.assigned_to');
    expect($select instanceof Select)->toBeTrue();
    /** @var Select $select */
    expect($select->getOptions())->not->toHaveKey($scopeOnly->id);

    // and the client cannot force the id past the server
    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->callAction('create_task', ['title' => 'Kurgusal zorlanan kapsam', 'assigned_to' => $scopeOnly->id])
        ->assertHasActionErrors(['assigned_to']);

    expect(Task::query()->where('title', 'Kurgusal zorlanan kapsam')->count())->toBe(0);
});

it('deal.view izni olmayan kullanıcı dosya satırını, referansını ve sayısını görmez', function (): void {
    $fixture = customerWorkspaceFixture();
    $company = $fixture['company'];

    $dealStatus = Status::query()->where('type', 'deal')->where('is_initial', true)->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();
    Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $dealStatus->id,
        'reference_no' => 'IZINSIZ-DOSYA',
        'opened_by_user_id' => $fixture['owner']->id,
        'status_changed_at' => now(),
    ]);

    // company.manage lets the screen open; deal.view is deliberately absent
    $viewer = customerWorkspaceUser('Kurgusal Dosya Izinsiz', 'dosya-izinsiz@example.invalid', ['company.manage']);
    Auth::login($viewer);

    $summary = CompanyWorkspaceSummary::for($company, $viewer);
    expect($summary->deals)->toHaveCount(0)
        ->and($summary->activeDeals)->toBe(0)
        ->and($summary->pendingDocuments)->toBe(0);

    $html = Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->call('setActiveTab', 'files')
        ->html();

    expect($html)->not->toContain('IZINSIZ-DOSYA');

    // the row still exists; only this viewer is denied
    expect(Deal::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('lead.manage ve task.manage izinleri yoksa fırsat ve görev satırları varsayılan olarak reddedilir', function (): void {
    $fixture = customerWorkspaceFixture();
    $company = $fixture['company'];

    $leadStatus = Status::query()->where('type', 'lead')->where('is_initial', true)->firstOrFail();
    $version = ProgramVersion::query()->firstOrFail();
    Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $fixture['owner']->id,
        'status_id' => $leadStatus->id,
        'interested_program_version_id' => $version->id,
        'source' => 'other',
    ]);
    Task::query()->create([
        'company_id' => $company->id,
        'assigned_to' => $fixture['owner']->id,
        'created_by' => $fixture['owner']->id,
        'title' => 'IZINSIZ-GOREV',
    ]);

    $viewer = customerWorkspaceUser('Kurgusal Firsat Izinsiz', 'firsat-izinsiz@example.invalid', ['company.manage']);
    Auth::login($viewer);

    $summary = CompanyWorkspaceSummary::for($company, $viewer);
    expect($summary->leads)->toHaveCount(0)
        ->and($summary->openLeads)->toBe(0)
        ->and($summary->tasks)->toHaveCount(0)
        ->and($summary->openTasks)->toBe(0)
        ->and($summary->overdueTasks)->toBe(0);

    $html = Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
        ->call('setActiveTab', 'tasks')
        ->html();

    expect($html)->not->toContain('IZINSIZ-GOREV');

    // granting the permissions is what makes the rows appear
    $viewer->givePermissionTo('lead.manage');
    $viewer->givePermissionTo('task.manage');
    $granted = CompanyWorkspaceSummary::for($company, $viewer->fresh() ?? $viewer);

    expect($granted->leads)->toHaveCount(1)
        ->and($granted->tasks)->toHaveCount(1);
});
