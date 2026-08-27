<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Crm\Actions\CreateCompanyOpportunity;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

it('mevcut firmadan fırsat açar ve aramayı planlar', function (): void {
    $actor = User::factory()->create(['email' => 'firma-firsat@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Fırsat Firması', 'industry' => 'services', 'city' => 'Ankara', 'owner_user_id' => $actor->id]);
    $contact = Contact::query()->create(['company_id' => $company->id, 'full_name' => 'Kurgusal Görüşülecek Kişi', 'data_source' => 'other']);
    $version = ProgramVersion::query()->where('is_active', true)->firstOrFail();
    $nextCallAt = now()->addDay()->startOfMinute();

    $lead = app(CreateCompanyOpportunity::class)->execute($company->id, $version->id, $actor, $contact->id, $nextCallAt->toDateTimeString());

    expect($lead->company_id)->toBe($company->id)
        ->and($lead->primary_contact_id)->toBe($contact->id)
        ->and($lead->interested_program_version_id)->toBe($version->id)
        ->and($lead->next_call_at?->equalTo($nextCallAt))->toBeTrue()
        ->and(StatusHistory::query()->where('lead_id', $lead->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('lead_id', $lead->id)->where('action', 'lead.created')->exists())->toBeTrue();
});

it('başka firmaya ait kişiyi fırsata bağlamayı reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'firma-firsat-koruma@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Asıl Firma', 'industry' => 'services', 'city' => 'Ankara', 'owner_user_id' => $actor->id]);
    $other = Company::query()->create(['legal_name' => 'Kurgusal Yabancı Firma', 'industry' => 'food', 'city' => 'İzmir', 'owner_user_id' => $actor->id]);
    $foreignContact = Contact::query()->create(['company_id' => $other->id, 'full_name' => 'Kurgusal Yabancı Kişi', 'data_source' => 'other']);
    $version = ProgramVersion::query()->where('is_active', true)->firstOrFail();

    expect(fn () => app(CreateCompanyOpportunity::class)->execute($company->id, $version->id, $actor, $foreignContact->id))
        ->toThrow(ValidationException::class)
        ->and(Lead::query()->where('company_id', $company->id)->exists())->toBeFalse();
});

it('firma listesi ve detayında yalnız arama planlama aksiyonunu gösterir', function (): void {
    $actor = User::factory()->create(['email' => 'firma-firsat-ekran@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Ekran Firması', 'industry' => 'services', 'city' => 'Bursa', 'owner_user_id' => $actor->id]);
    Auth::login($actor);

    Livewire::test(ListCompanies::class)->assertTableActionExists(
        'schedule_call',
        fn (Action $action): bool => $action->getLabel() === 'Arama planla',
        record: $company,
    );
    Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])->assertActionExists(
        'schedule_call',
        fn (Action $action): bool => $action->getLabel() === 'Arama planla',
    );
});

it('arama planlamada tarih verilmeden kayıt oluşturmaz', function (): void {
    $actor = User::factory()->create(['email' => 'arama-tarihi-zorunlu@example.invalid']);
    $actor->assignRole('Pazarlama');
    $company = Company::query()->create(['legal_name' => 'Kurgusal Tarih Kontrollü Firma', 'industry' => 'services', 'owner_user_id' => $actor->id]);
    $version = ProgramVersion::query()->where('is_active', true)->firstOrFail();
    Auth::login($actor);

    Livewire::test(ListCompanies::class)
        ->callTableAction('schedule_call', $company, [
            'program_version_id' => $version->id,
            'contact_id' => null,
            'next_call_at' => null,
        ])
        ->assertHasActionErrors(['next_call_at' => 'required']);

    expect(Lead::query()->where('company_id', $company->id)->exists())->toBeFalse();
});
