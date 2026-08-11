<?php

declare(strict_types=1);

use App\Domain\Crm\Models\CommunicationConsent;
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
function marketingScreenLead(User $owner, string $suffix, string $statusCode = 'called', ?string $nextCallAt = null, bool $blocked = false): array
{
    $company = Company::query()->create(['legal_name' => "Kurgusal Ekran {$suffix}", 'city' => '34']);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => "Kurgusal Kişi {$suffix}",
        'data_source' => 'list',
        'phone' => '+90 000 000 00 00',
        'consent_call' => ! $blocked,
        'do_not_call' => $blocked,
        'is_primary' => true,
    ]);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'call',
        'purpose' => 'marketing',
        'status' => $blocked ? 'withdrawn' : 'granted',
        'legal_basis' => $blocked ? 'explicit_withdrawal' : 'explicit_consent',
        'source' => 'list',
        'disclosure_date' => now()->subDays(5)->toDateString(),
        'disclosure_method' => 'phone',
        'effective_from' => now()->subDays(2),
        'recorded_by' => $owner->id,
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

it('aranmaması gereken kişide tel aksiyonunu engeller ve sebebini gösterir', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-ret@example.invalid']);
    $owner->assignRole('Pazarlama');
    marketingScreenLead($owner, 'Ret', nextCallAt: now()->subHour()->toDateTimeString(), blocked: true);
    Auth::login($owner);

    Livewire::test(TodayCalls::class)
        ->assertSee('Arama engellendi')
        ->assertSee('Ret tarihi')
        ->assertDontSeeHtml('href="tel:');
});

it('bugün ve geçmiş aramaları geciken önce sıralar ve geleceği dışarıda bırakır', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-siralama@example.invalid']);
    $owner->assignRole('Pazarlama');
    $overdue = marketingScreenLead($owner, 'Geciken', nextCallAt: now()->subDays(2)->toDateTimeString());
    $today = marketingScreenLead($owner, 'Bugün', nextCallAt: now()->addHour()->toDateTimeString());
    $future = marketingScreenLead($owner, 'Gelecek', nextCallAt: now()->addDays(2)->toDateTimeString());
    Interaction::query()->create([
        'lead_id' => $overdue['lead']->id,
        'user_id' => $owner->id,
        'type' => 'call',
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

it('kişi kartında izin kaynağını gösterir ve kaynak seçmeden yeni kişi kaydetmez', function (): void {
    $owner = User::factory()->create(['email' => 'ekran-kisi@example.invalid']);
    $owner->assignRole('Pazarlama');
    $fixture = marketingScreenLead($owner, 'Kişi Kartı');
    Auth::login($owner);

    Livewire::test(ViewCompany::class, ['record' => $fixture['company']->getRouteKey()])
        ->assertSee('Veri kaynağı')
        ->assertSee('Arama izni var')
        ->set('contactFullName', 'Kurgusal Yeni Yetkili')
        ->set('contactDataSource', '')
        ->call('addContact')
        ->assertHasErrors(['contactDataSource' => 'required']);
});
