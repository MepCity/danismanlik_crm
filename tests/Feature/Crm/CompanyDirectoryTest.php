<?php

declare(strict_types=1);

use App\Domain\Collaboration\Jobs\SendNotificationEmail;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification as CollaborationNotification;
use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Domain\Crm\Actions\StartCustomerFlow;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Services\BulkCompanyEmailService;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    app()->setLocale('tr');
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

it('müşteri olmayan firmayı sektör ve sorumlusuyla rehbere ekler', function (): void {
    $actor = User::factory()->create(['email' => 'rehber-pazarlama@example.invalid']);
    $actor->assignRole('Pazarlama');

    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Gıda Üretim AŞ',
        'industry' => 'food',
        'city' => 'Konya',
        'district' => 'Selçuklu',
        'tax_number' => null,
        'tax_office' => null,
        'nace_code' => null,
        'size' => 'small',
        'employee_count' => 24,
        'is_active' => true,
    ], $actor);

    expect($company->owner_user_id)->toBe($actor->id)
        ->and($company->industry)->toBe('food')
        ->and($company->deals()->count())->toBe(0)
        ->and(app(ScopedQuery::class)->contains($actor, $company, 'view'))->toBeTrue()
        ->and(Activity::query()->where('company_id', $company->id)->where('action', 'company.created')->exists())->toBeTrue();
});

it('kontrolsüz sektör kodunu firma rehberine kaydetmez', function (): void {
    $actor = User::factory()->create(['email' => 'rehber-sektor-ret@example.invalid']);
    $actor->assignRole('Pazarlama');

    expect(fn () => app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Geçersiz Sektör AŞ',
        'industry' => 'rastgele-serbest-metin',
        'city' => 'Ankara',
        'is_active' => true,
    ], $actor))->toThrow(ValidationException::class);

    expect(Company::query()->where('legal_name', 'Kurgusal Geçersiz Sektör AŞ')->exists())->toBeFalse();
});

it('firma rehberi ekranında sektör alanını zorunlu tutar ve sektörle filtreler', function (): void {
    $actor = User::factory()->create(['email' => 'rehber-ekran@example.invalid']);
    $actor->assignRole('Pazarlama');
    Auth::login($actor);

    Livewire::test(CreateCompany::class)
        ->fillForm([
            'legal_name' => 'Kurgusal Ekran Gıda AŞ',
            'industry' => 'food',
            'city' => 'Bursa',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $food = Company::query()->where('legal_name', 'Kurgusal Ekran Gıda AŞ')->sole();
    app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Yazılım AŞ',
        'industry' => 'software',
        'city' => 'İstanbul',
        'is_active' => true,
    ], $actor);

    Livewire::test(ListCompanies::class)
        ->filterTable('industry', 'food')
        ->assertTableBulkActionExists('send_bulk_email')
        ->assertSee($food->legal_name)
        ->assertDontSee('Kurgusal Yazılım AŞ');

    expect(CompanyResource::getNavigationLabel())->toBe('Firma rehberi');
});

it('firma rehberinde unvan ve sektör dışındaki alanları isteğe bağlı tutar', function (): void {
    $actor = User::factory()->create(['email' => 'rehber-hizli@example.invalid']);
    $actor->assignRole('Pazarlama');
    Auth::login($actor);

    Livewire::test(CreateCompany::class)
        ->fillForm([
            'legal_name' => 'Kurgusal Hızlı Kayıt AŞ',
            'industry' => 'metal',
            'city' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Company::query()->where('legal_name', 'Kurgusal Hızlı Kayıt AŞ')->sole()->city)->toBeNull();
});

it('toplu e-postada yalnız güncel izni olan kişileri kuyruğa alır ve filtreyi denetler', function (): void {
    Queue::fake();
    $actor = User::factory()->create(['email' => 'toplu-posta@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Metal Sanayi AŞ',
        'industry' => 'metal',
        'city' => null,
        'is_active' => true,
    ], $actor);
    $allowed = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İzinli Kişi',
        'email' => 'izinli@example.invalid',
        'data_source' => 'other',
        'consent_email' => true,
    ]);
    $revoked = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İzinsiz Kişi',
        'email' => 'izinsiz@example.invalid',
        'data_source' => 'other',
        'consent_email' => true,
    ]);
    Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal E-postasız Kişi',
        'data_source' => 'other',
        'consent_email' => true,
    ]);
    foreach ([[$allowed, 'granted'], [$revoked, 'granted'], [$revoked, 'withdrawn']] as $index => [$contact, $status]) {
        CommunicationConsent::query()->create([
            'contact_id' => $contact->id,
            'channel' => 'email',
            'purpose' => 'marketing',
            'status' => $status,
            'legal_basis' => $status === 'granted' ? 'explicit_consent' : 'withdrawal',
            'source' => 'form',
            'effective_from' => now()->subSeconds(2 - $index),
            'recorded_by' => $actor->id,
        ]);
    }

    $result = app(BulkCompanyEmailService::class)->send(
        Company::query()->whereKey($company->id)->get(),
        '{{firma_adi}} için destek',
        'Merhaba {{yetkili_adi}}, {{firma_adi}} için bilgi.',
        ['industry' => ['value' => 'metal']],
        $actor,
    );

    $notification = CollaborationNotification::query()->where('type', 'company.bulk_email')->sole();
    $activity = Activity::query()->where('company_id', $company->id)->where('action', 'company.bulk_email_requested')->sole();

    expect($result->queuedCount)->toBe(1)
        ->and($result->consentRejectedCount)->toBe(1)
        ->and($result->missingEmailCount)->toBe(1)
        ->and($notification->recipient_email)->toBe('izinli@example.invalid')
        ->and($notification->title)->toContain('Kurgusal Metal Sanayi AŞ')
        ->and($notification->body)->toContain('Kurgusal İzinli Kişi')
        ->and(data_get($activity->payload, 'queued_recipient_count'))->toBe(1)
        ->and(data_get($activity->payload, 'filters.industry.value'))->toBe('metal');
    Queue::assertPushed(SendNotificationEmail::class, 1);
});

it('toplu e-postayı yetkisiz rol için sunucu tarafında reddeder', function (): void {
    $owner = User::factory()->create(['email' => 'toplu-posta-sahibi@example.invalid']);
    $owner->assignRole('Pazarlama');
    $projectManager = User::factory()->create(['email' => 'toplu-posta-yetkisiz@example.invalid']);
    $projectManager->assignRole('Proje Yöneticisi');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Yetki Denetimli Metal AŞ',
        'industry' => 'metal',
        'city' => null,
        'is_active' => true,
    ], $owner);

    expect(fn () => app(BulkCompanyEmailService::class)->send(
        Company::query()->whereKey($company->id)->get(),
        'Kurgusal konu',
        'Kurgusal gövde',
        [],
        $projectManager,
    ))->toThrow(AuthorizationException::class);
    expect(CollaborationNotification::query()->where('type', 'company.bulk_email')->exists())->toBeFalse();
});

it('firma listesindeki seçimden toplu e-posta eylemini çalıştırır', function (): void {
    Queue::fake();
    $actor = User::factory()->create(['email' => 'toplu-posta-ekran@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Ekran Ambalaj AŞ',
        'industry' => 'packaging',
        'city' => null,
        'is_active' => true,
    ], $actor);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal Ekran Yetkilisi',
        'email' => 'ekran-yetkilisi@example.invalid',
        'data_source' => 'other',
        'consent_email' => true,
    ]);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'email',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'explicit_consent',
        'source' => 'form',
        'effective_from' => now()->subMinute(),
        'recorded_by' => $actor->id,
    ]);
    Auth::login($actor);

    Livewire::test(ListCompanies::class)
        ->filterTable('industry', 'packaging')
        ->callTableBulkAction('send_bulk_email', [$company], [
            'subject' => '{{firma_adi}} duyurusu',
            'body' => 'Merhaba {{yetkili_adi}}',
        ])
        ->assertHasNoActionErrors();

    expect(CollaborationNotification::query()->where('recipient_email', 'ekran-yetkilisi@example.invalid')->exists())->toBeTrue();
});

it('mevcut firmadan müşteri akışı başlatıp evrak listesini üretir', function (): void {
    $actor = User::factory()->create(['email' => 'musteri-akis@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Anlaşılan Firma AŞ',
        'industry' => 'manufacturing',
        'city' => 'Kocaeli',
        'is_active' => true,
    ], $actor);
    $version = ProgramVersion::query()->firstOrFail();

    $dealId = app(StartCustomerFlow::class)->execute($company->id, $version->id, $actor);
    $deal = Deal::query()->findOrFail($dealId);

    expect($deal->company_id)->toBe($company->id)
        ->and($deal->program_version_id)->toBe($version->id)
        ->and($deal->pm_user_id)->toBeNull()
        ->and(DealDocument::query()->where('deal_id', $deal->id)->exists())->toBeTrue()
        ->and(Company::query()->whereKey($company->id)->count())->toBe(1)
        ->and(Activity::query()->where('company_id', $company->id)->where('action', 'company.customer_flow_started')->exists())->toBeTrue();

    Auth::login($actor);
    Livewire::test(ListCustomers::class)
        ->assertSee($company->legal_name)
        ->assertSee('İmalat');
});

it('müşteri akışını yetkisiz rol için sunucu tarafında reddeder', function (): void {
    $owner = User::factory()->create(['email' => 'musteri-sahibi@example.invalid']);
    $owner->assignRole('Pazarlama');
    $projectManager = User::factory()->create(['email' => 'musteri-yetkisiz@example.invalid']);
    $projectManager->assignRole('Proje Yöneticisi');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal Yetki Kontrollü Firma',
        'industry' => 'services',
        'city' => 'Ankara',
        'is_active' => true,
    ], $owner);
    $version = ProgramVersion::query()->firstOrFail();

    expect(fn () => app(StartCustomerFlow::class)->execute($company->id, $version->id, $projectManager))
        ->toThrow(HttpException::class);
    expect(Deal::query()->where('company_id', $company->id)->exists())->toBeFalse();
});
