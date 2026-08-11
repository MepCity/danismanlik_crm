<?php

declare(strict_types=1);

use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects updates to communication consents', function (): void {
    $user = User::factory()->create(['email' => 'consent-update@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Alfa Ltd.', 'city' => '06']);
    $contact = Contact::query()->create(['company_id' => $company->id, 'full_name' => 'Kurgusal Yetkili']);
    $consent = CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'call',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'Kurgusal açık onay',
        'source' => 'form',
        'effective_from' => now(),
        'recorded_by' => $user->id,
    ]);

    expect(fn () => $consent->update(['status' => 'withdrawn']))
        ->toThrow(QueryException::class, 'append-only');
});

it('rejects deletes from communication consents', function (): void {
    $user = User::factory()->create(['email' => 'consent-delete@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Beta Ltd.', 'city' => '34']);
    $contact = Contact::query()->create(['company_id' => $company->id, 'full_name' => 'Kurgusal İrtibat']);
    $consent = CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'email',
        'purpose' => 'service',
        'status' => 'denied',
        'legal_basis' => 'Kurgusal hizmet kaydı',
        'source' => 'phone',
        'effective_from' => now(),
        'recorded_by' => $user->id,
    ]);

    expect(fn () => $consent->delete())
        ->toThrow(QueryException::class, 'append-only');
});

it('enforces unique tax numbers while allowing multiple null values', function (): void {
    Company::query()->create(['legal_name' => 'Kurgusal Gama Ltd.', 'tax_number' => null, 'city' => '35']);
    Company::query()->create(['legal_name' => 'Kurgusal Delta Ltd.', 'tax_number' => null, 'city' => '16']);
    Company::query()->create(['legal_name' => 'Kurgusal Epsilon Ltd.', 'tax_number' => '0000000000', 'city' => '01']);

    expect(Company::query()->whereNull('tax_number')->count())->toBe(2)
        ->and(fn () => Company::query()->create([
            'legal_name' => 'Kurgusal Zeta Ltd.',
            'tax_number' => '0000000000',
            'city' => '07',
        ]))->toThrow(QueryException::class);
});

it('accepts only controlled city codes', function (): void {
    expect(fn () => Company::query()->create([
        'legal_name' => 'Kurgusal İl Testi Ltd.',
        'city' => '82',
    ]))->toThrow(QueryException::class, 'companies_city_code');
});

it('accepts only ten or eleven digit tax numbers', function (): void {
    expect(fn () => Company::query()->create([
        'legal_name' => 'Kurgusal Vergi Biçimi Ltd.',
        'tax_number' => 'ABC0000000',
        'city' => '45',
    ]))->toThrow(QueryException::class, 'companies_tax_number_format');
});

it('allows only one primary contact per company', function (): void {
    $company = Company::query()->create(['legal_name' => 'Kurgusal Eta Ltd.', 'city' => '46']);
    Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal Birincil A',
        'is_primary' => true,
    ]);

    expect(fn () => Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal Birincil B',
        'is_primary' => true,
    ]))->toThrow(QueryException::class, 'contacts_one_primary_per_company');
});

it('requires a reason for lost leads', function (): void {
    $owner = User::factory()->create(['email' => 'lost-owner@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Teta Ltd.', 'city' => '26']);

    expect(fn () => Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status' => 'lost',
    ]))->toThrow(QueryException::class, 'leads_lost_reason_required');
});

it('requires a date for callback leads', function (): void {
    $owner = User::factory()->create(['email' => 'callback-owner@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Iota Ltd.', 'city' => '42']);

    expect(fn () => Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'status' => 'callback',
    ]))->toThrow(QueryException::class, 'leads_callback_date_required');
});

it('restricts deletion of companies referenced by contacts', function (): void {
    $company = Company::query()->create(['legal_name' => 'Kurgusal Kappa Ltd.', 'city' => '55']);
    Contact::query()->create(['company_id' => $company->id, 'full_name' => 'Kurgusal Bağlı Kişi']);

    expect(fn () => $company->delete())->toThrow(QueryException::class);
});

it('restricts deletion of contacts referenced by consents', function (): void {
    $user = User::factory()->create(['email' => 'restrict-recorder@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Lambda Ltd.', 'city' => '61']);
    $contact = Contact::query()->create(['company_id' => $company->id, 'full_name' => 'Kurgusal Kayıt Sahibi']);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'sms',
        'purpose' => 'marketing',
        'status' => 'withdrawn',
        'legal_basis' => 'Kurgusal geri alma',
        'source' => 'iys',
        'effective_from' => now(),
        'recorded_by' => $user->id,
    ]);

    expect(fn () => $contact->delete())->toThrow(QueryException::class);
});

it('requires exactly one interaction subject', function (): void {
    $user = User::factory()->create(['email' => 'interaction-type@example.invalid']);

    expect(fn () => Interaction::query()->create([
        'user_id' => $user->id,
        'type' => 'call',
        'occurred_at' => now(),
    ]))->toThrow(QueryException::class, 'interactions_exactly_one_subject');
});
