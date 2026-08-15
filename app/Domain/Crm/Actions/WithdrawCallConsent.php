<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Contact;
use Illuminate\Support\Facades\DB;

final class WithdrawCallConsent
{
    public function handle(int $contactId, int $actorId): CommunicationConsent
    {
        return DB::transaction(function () use ($contactId, $actorId): CommunicationConsent {
            $contact = Contact::query()->lockForUpdate()->findOrFail($contactId);
            $consent = CommunicationConsent::query()->create([
                'contact_id' => $contact->id,
                'channel' => 'call',
                'purpose' => 'marketing',
                'status' => 'withdrawn',
                'legal_basis' => 'explicit_withdrawal',
                'source' => 'phone',
                'disclosure_date' => now()->toDateString(),
                'effective_from' => now(),
                'recorded_by' => $actorId,
            ]);

            $contact->update(['consent_call' => false, 'do_not_call' => true]);

            return $consent;
        });
    }
}
