<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Contact;
use App\Models\User;
use App\Support\Audit\ActorSource;

final readonly class WithdrawEmailConsent
{
    public function __construct(private CollaborationTransaction $transactions) {}

    public function execute(Contact $contact): CommunicationConsent
    {
        $latest = $contact->communicationConsents()
            ->where('channel', 'email')
            ->where('purpose', 'marketing')
            ->where('effective_from', '<=', now())
            ->latest('effective_from')
            ->latest('id')
            ->first();

        if ($latest?->status === 'withdrawn') {
            return $latest;
        }

        $systemActor = User::query()->where('email', (string) config('operations.system_actor_email'))->firstOrFail();

        return $this->transactions->run(ActorSource::Automation, $systemActor->id, function () use ($contact, $systemActor): CommunicationConsent {
            $consent = CommunicationConsent::query()->create([
                'contact_id' => $contact->id,
                'channel' => 'email',
                'purpose' => 'marketing',
                'status' => 'withdrawn',
                'legal_basis' => 'withdrawal',
                'source' => 'form',
                'evidence' => ['method' => 'signed_unsubscribe_link'],
                'effective_from' => now(),
                'recorded_by' => $systemActor->id,
            ]);
            $contact->update(['consent_email' => false]);

            return $consent;
        });
    }
}
