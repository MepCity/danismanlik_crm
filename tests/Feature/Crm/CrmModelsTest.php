<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('connects a company to contacts and a lead to interactions', function (): void {
    $owner = User::factory()->create(['email' => 'relations-owner@example.invalid']);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Mu Ltd.',
        'tax_number' => '00000000000',
        'city' => 'Hatay',
    ]);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İlişki Kişisi',
        'data_source' => 'other',
        'phone' => '+900000000000',
        'email' => 'contact@example.invalid',
    ]);
    $program = Program::query()->create([
        'name' => 'Kurgusal Dönüşüm Desteği',
        'institution' => 'other',
        'code' => 'KURGUSAL-DONUSUM',
    ]);
    $programVersion = $program->versions()->create([
        'call_period' => '2099-1',
    ]);
    $status = Status::query()->create([
        'code' => 'crm_model_new',
        'label' => 'Kurgusal Yeni',
        'type' => 'lead',
        'color' => 'neutral',
    ]);
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $owner->id,
        'source' => 'fictional_test',
        'interested_program_version_id' => $programVersion->id,
        'status_id' => $status->id,
    ]);
    $interaction = $lead->interactions()->create([
        'user_id' => $owner->id,
        'type' => 'call',
        'direction' => 'outbound',
        'purpose' => 'marketing',
        'occurred_at' => now(),
        'outcome' => 'Kurgusal görüşme sonucu',
    ]);

    expect($company->contacts->modelKeys())->toBe([$contact->id])
        ->and($contact->company->is($company))->toBeTrue()
        ->and($company->leads->modelKeys())->toBe([$lead->id])
        ->and($lead->company->is($company))->toBeTrue()
        ->and($lead->owner->is($owner))->toBeTrue()
        ->and($lead->status->is($status))->toBeTrue()
        ->and($lead->interactions->modelKeys())->toBe([$interaction->id])
        ->and($interaction->lead_id)->toBe($lead->id)
        ->and($interaction->deal_id)->toBeNull()
        ->and($interaction->lead->is($lead))->toBeTrue()
        ->and($interaction->user->is($owner))->toBeTrue();
});
