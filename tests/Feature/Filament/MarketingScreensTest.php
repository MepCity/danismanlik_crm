<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Pages\LeadBoard;
use App\Filament\Pages\LeadDetail;
use App\Filament\Pages\TodayCalls;
use App\Filament\Resources\Companies\Pages\ViewCompany;
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

/** @return array{owner: User, company: Company, contact: Contact, lead: Lead} */
function marketingScreenLead(User $owner, string $suffix, string $statusCode = 'called', ?string $nextCallAt = null): array
{
    $company = Company::query()->create(['legal_name' => "Kurgusal Ekran {$suffix}", 'city' => 'İstanbul']);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => "Kurgusal Kişi {$suffix}",
        'data_source' => 'list',
        'phone' => '+90 000 000 00 00',
        'is_primary' => true,
    ]);
    $status = Status::query()->where('type', 'lead')->where('code', $statusCode)->sole();
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'interested_program_version_id' => ProgramVersion::query()->firstOrFail()->id,
        'status_id' => $status->id,
        'next_call_at' => $nextCallAt,
    ]);
    StatusHistory::query()->create([
        'lead_id' => $lead->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'workflow_revision_id' => WorkflowRevision::query()->firstOrFail()->id,
        'entered_at' => now()->subDays(2),
        'changed_by' => $owner->id,
    ]);

    return compact('owner', 'company', 'contact', 'lead');
}

it('pazarlamacıya yalnız kendi fırsatlarını listeler ve doğrudan URL erişimini 403 reddeder', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-sahibi@example.invalid']);
    $owner->assignRole('Pazarlama');
    $other = User::factory()->create(['email' => 'ekran-diger@example.invalid']);
    $other->assignRole('Pazarlama');
    $own = marketingScreenLead($owner, 'Kendi');
    $foreign = marketingScreenLead($other, 'Başkasının');
    Auth::login($owner);

    Livewire::test(LeadBoard::class)
        ->assertSee($own['company']->legal_name)
        ->assertDontSee($foreign['company']->legal_name);
    Livewire::test(LeadDetail::class, ['lead' => $foreign['lead']->id])->assertForbidden();
});

it('takip panosunda kartı ileri ve geri taşıyıp aktör izini korur', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-surukle@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Sürükle');
    $called = Status::query()->where('type', 'lead')->where('code', 'called')->sole();
    $interested = Status::query()->where('type', 'lead')->where('code', 'interested')->sole();
    Auth::login($owner);

    Livewire::test(LeadBoard::class)
        ->call('openLead', $fixture['lead']->id)
        ->assertSet('selectedLeadId', $fixture['lead']->id)
        ->assertSee('Kurgusal Ekran Sürükle')
        ->assertSee('lead-detail-drawer')
        ->call('moveLead', $fixture['lead']->id, $interested->id)
        ->assertHasNoErrors();

    expect($fixture['lead']->refresh()->status_id)->toBe($interested->id);

    Livewire::test(LeadBoard::class)
        ->call('moveLead', $fixture['lead']->id, $called->id)
        ->assertHasNoErrors();

    expect($fixture['lead']->refresh()->status_id)->toBe($called->id)
        ->and($fixture['lead']->statusHistory()->latest('id')->value('changed_by'))->toBe($owner->id);
});

it('takip panosunda alan isteyen bırakmayı veri formuna yönlendirir', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-surukle-form@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Sürükle Form');
    $callback = Status::query()->where('type', 'lead')->where('code', 'callback')->sole();
    Auth::login($owner);

    Livewire::test(LeadBoard::class)
        ->call('moveLead', $fixture['lead']->id, $callback->id)
        ->assertSet('transitionLeadId', $fixture['lead']->id)
        ->assertSet('transitionTargetId', $callback->id)
        ->assertSee('lead-transition-form');
});

it('arama izni seçeneği olmadan tel ve sonuç aksiyonlarını gösterir', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-arama@example.invalid']);
    $owner->assignRole('Pazarlama');
    marketingScreenLead($owner, 'Arama', nextCallAt: now()->subHour()->toDateTimeString());
    Auth::login($owner);

    Livewire::test(TodayCalls::class)
        ->assertDontSee('Arama izni')
        ->assertSeeHtml('href="tel:')
        ->call('chooseOutcome', Lead::query()->sole()->id, 'contacted')
        ->assertSee('Ulaşılamadı')
        ->assertSee('Görüşüldü');
});

it('gelen aramayı ayrı işaretle kaydeder', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-gelen@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Gelen');
    Auth::login($owner);

    Livewire::test(LeadDetail::class, ['lead' => $fixture['lead']->id])
        ->set('interactionType', 'incoming_call')
        ->set('interactionOutcome', 'Kurgusal gelen arama')
        ->call('addInteraction')
        ->assertHasNoErrors();

    expect(Interaction::query()->where('lead_id', $fixture['lead']->id)->sole()->direction)->toBe('inbound')
        ->and(Interaction::query()->where('lead_id', $fixture['lead']->id)->sole()->purpose)->toBe('marketing');
});

it('fırsat detayını tek sayfada sol sağ bilgiler ve birleşik etkinlikle gösterir', function (): void {
    $owner = User::factory()->create(['name' => 'Kurgusal Fırsat Sahibi', 'email' => 'ekran-tek-sayfa@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Tek Sayfa');
    Auth::login($owner);

    Livewire::test(LeadDetail::class, ['lead' => $fixture['lead']->id])
        ->assertSee('Temel bilgiler')
        ->assertSee('Kişiler')
        ->assertSee('Görüşmeler')
        ->assertSee('Ayrıntılar')
        ->assertSee('Etkinlik')
        ->assertSee('Yorumlar')
        ->assertSee('Geçmiş')
        ->assertSee('Tümü')
        ->assertSee($fixture['company']->legal_name)
        ->assertSee($fixture['contact']->full_name)
        ->assertDontSeeHtml('class="deal-tabs"')
        ->call('setActivityFilter', 'history')
        ->assertSet('activityFilter', 'history')
        ->call('setActivityFilter', 'all')
        ->assertSet('activityFilter', 'all');
});

it('fırsat etkinliğinde tanımsız filtreyi reddeder', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-filtre-ret@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Filtre Ret');
    Auth::login($owner);

    Livewire::test(LeadDetail::class, ['lead' => $fixture['lead']->id])
        ->call('setActivityFilter', 'raw-audit')
        ->assertStatus(422);
});

it('bugün ve geçmiş aramaları geciken önce sıralar ve geleceği dışarıda bırakır', function (): void {
    /** @var TestCase $this */
    $this->travelTo(now()->startOfDay()->addHours(12));

    $owner = User::factory()->create(['email' => 'ekran-siralama@example.invalid']);
    $owner->assignRole('Pazarlama');
    $overdue = marketingScreenLead($owner, 'Geciken', nextCallAt: now()->subDays(2)->toDateTimeString());
    $today = marketingScreenLead($owner, 'Bugün', nextCallAt: now()->addHour()->toDateTimeString());
    $future = marketingScreenLead($owner, 'Gelecek', nextCallAt: now()->addDays(2)->toDateTimeString());
    Interaction::query()->create([
        'lead_id' => $overdue['lead']->id,
        'user_id' => $owner->id,
        'type' => 'call',
        'direction' => 'outbound',
        'purpose' => 'marketing',
        'occurred_at' => now()->subDay(),
        'outcome' => 'unreachable',
    ]);
    Auth::login($owner);

    Livewire::test(TodayCalls::class)
        ->assertSeeInOrder([$overdue['company']->legal_name, $today['company']->legal_name])
        ->assertDontSee($future['company']->legal_name)
        ->assertSee('2. arama')
        ->assertSeeHtml('href="tel:');
});

it('hazır sonucu iki adımda ayrı görüşme olarak kaydeder', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-hizli@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Hızlı', nextCallAt: now()->toDateTimeString());
    $statusId = $fixture['lead']->status_id;
    Auth::login($owner);

    Livewire::test(TodayCalls::class)
        ->call('chooseOutcome', $fixture['lead']->id, 'interested')
        ->set('quickNote', 'Kurgusal kısa not')
        ->call('saveOutcome')
        ->assertHasNoErrors();

    expect(Interaction::query()->where('lead_id', $fixture['lead']->id)->where('outcome', 'interested')->count())->toBe(1)
        ->and($fixture['lead']->refresh()->status_id)->toBe($statusId);
});

it('callback ve lost geçiş formlarında açıklayıcı zorunlu alan hataları gösterir', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-form@example.invalid']);
    $owner->assignRole('Pazarlama');
    $callbackLead = marketingScreenLead($owner, 'Callback Form');
    $callback = Status::query()->where('type', 'lead')->where('code', 'callback')->sole();
    $lostLead = marketingScreenLead($owner, 'Lost Form', 'interested');
    $lost = Status::query()->where('type', 'lead')->where('code', 'lost')->sole();
    Auth::login($owner);

    Livewire::test(LeadBoard::class)
        ->call('beginTransition', $callbackLead['lead']->id, $callback->id)
        ->set('nextCallAt', null)
        ->set('ownerUserId', null)
        ->call('saveTransition')
        ->assertHasErrors(['nextCallAt', 'ownerUserId']);

    Livewire::test(LeadBoard::class)
        ->call('beginTransition', $lostLead['lead']->id, $lost->id)
        ->set('lostReason', '')
        ->call('saveTransition')
        ->assertHasErrors(['lostReason']);
});

it('veri kaynağını göstermeden yeni kişiyi sistem kaynağıyla kaydeder', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-kisi@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Kişi Kartı');
    Auth::login($owner);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->set('activeTab', 'contacts')
        ->assertDontSee('Veri kaynağı')
        ->assertDontSee('Arama izni')
        ->assertDontSee('Bir daha aranmasın')
        ->set('contactFullName', 'Kurgusal Yeni Yetkili')
        ->call('addContact')
        ->assertHasNoErrors();

    expect(Contact::query()->where('full_name', 'Kurgusal Yeni Yetkili')->sole()->data_source)->toBe('other');
});
