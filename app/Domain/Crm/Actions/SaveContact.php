<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Contact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SaveContact
{
    public function create(
        int $companyId,
        int $actorId,
        string $fullName,
        ?string $phone = null,
        ?string $email = null,
        ?string $title = null,
        ?bool $callConsent = null,
        ?bool $emailConsent = null,
        ?string $disclosureDate = null,
        bool $isPrimary = false,
        ?Carbon $consentEffectiveAt = null,
        string $recordSource = 'other',
    ): Contact {
        $source = $this->ledgerSource(trim($recordSource));

        return DB::transaction(function () use ($companyId, $actorId, $fullName, $source, $phone, $email, $title, $callConsent, $emailConsent, $disclosureDate, $isPrimary, $consentEffectiveAt): Contact {
            $contact = Contact::query()->create([
                'company_id' => $companyId,
                'full_name' => trim($fullName),
                'data_source' => $source,
                'phone' => filled($phone) ? trim((string) $phone) : null,
                'email' => filled($email) ? trim((string) $email) : null,
                'title' => filled($title) ? trim((string) $title) : null,
                'is_primary' => $isPrimary,
                'is_active' => true,
                'consent_call' => $callConsent,
                'consent_email' => $emailConsent,
                'do_not_call' => $callConsent === false,
            ]);

            if ($callConsent !== null) {
                CommunicationConsent::query()->create([
                    'contact_id' => $contact->id,
                    'channel' => 'call',
                    'purpose' => 'marketing',
                    'status' => $callConsent ? 'granted' : 'denied',
                    'legal_basis' => $callConsent ? 'explicit_consent' : 'explicit_rejection',
                    'source' => $this->ledgerSource($source),
                    'disclosure_date' => $disclosureDate,
                    'effective_from' => $consentEffectiveAt ?? now(),
                    'recorded_by' => $actorId,
                ]);
            }

            if ($emailConsent !== null) {
                CommunicationConsent::query()->create([
                    'contact_id' => $contact->id,
                    'channel' => 'email',
                    'purpose' => 'marketing',
                    'status' => $emailConsent ? 'granted' : 'denied',
                    'legal_basis' => $emailConsent ? 'explicit_consent' : 'explicit_rejection',
                    'source' => $this->ledgerSource($source),
                    'disclosure_date' => $disclosureDate,
                    'effective_from' => $consentEffectiveAt ?? now(),
                    'recorded_by' => $actorId,
                ]);
            }

            return $contact;
        });
    }

    private function ledgerSource(string $source): string
    {
        return in_array($source, ['form', 'phone', 'list', 'referral', 'iys', 'other'], true) ? $source : 'other';
    }
}
