<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects a company to contacts and a lead to interactions', function (): void {
    $owner = User::factory()->create(['email' => 'relations-owner@example.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Mu Ltd.',
        'tax_number' => '00000000000',
        'city' => '31',
    ]);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İlişki Kişisi',
        'phone' => '+900000000000',
        'email' => 'contact@example.invalid',
        'consent_call' => true,
    ]);
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'source' => 'fictional_test',
        'interested_program' => 'KURGUSAL-PROGRAM',
        'status' => 'new',
    ]);
    $interaction = $lead->interactions()->create([
        'user_id' => $owner->id,
        'type' => 'call',
        'occurred_at' => now(),
        'duration_minutes' => 5,
        'outcome' => 'Kurgusal görüşme sonucu',
    ]);

    expect($company->contacts->modelKeys())->toBe([$contact->id])
        ->and($contact->company->is($company))->toBeTrue()
        ->and($company->leads->modelKeys())->toBe([$lead->id])
        ->and($lead->company->is($company))->toBeTrue()
        ->and($lead->owner->is($owner))->toBeTrue()
        ->and($lead->interactions->modelKeys())->toBe([$interaction->id])
        ->and($interaction->subject_type)->toBe('lead')
        ->and($interaction->subject->is($lead))->toBeTrue()
        ->and($interaction->user->is($owner))->toBeTrue()
        ->and($contact->consent_call)->toBeTrue();
});
