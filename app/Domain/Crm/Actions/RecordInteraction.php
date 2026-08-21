<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordInteraction
{
    public function __construct(private readonly ActivityRecorder $activities) {}

    public function forLead(int $leadId, int $actorId, string $type, Carbon $occurredAt, ?string $outcome, ?string $note, ?int $contactId = null): Interaction
    {
        return DB::transaction(function () use ($leadId, $actorId, $type, $occurredAt, $outcome, $note, $contactId): Interaction {
            return $this->create(['lead_id' => $leadId], $actorId, $type, $occurredAt, $outcome, $note, 'outbound', 'marketing', $contactId);
        });
    }

    public function forInboundLeadCall(int $leadId, int $actorId, Carbon $occurredAt, ?string $outcome, ?string $note, ?int $contactId = null): Interaction
    {
        return $this->create(['lead_id' => $leadId], $actorId, 'call', $occurredAt, $outcome, $note, 'inbound', 'marketing', $contactId);
    }

    public function forDeal(int $dealId, int $actorId, string $type, Carbon $occurredAt, ?string $outcome, ?string $note, ?int $contactId = null): Interaction
    {
        return $this->create(['deal_id' => $dealId], $actorId, $type, $occurredAt, $outcome, $note, 'outbound', 'service', $contactId);
    }

    /** @param array{lead_id?: int, deal_id?: int} $subject */
    private function create(array $subject, int $actorId, string $type, Carbon $occurredAt, ?string $outcome, ?string $note, string $direction, string $purpose, ?int $contactId = null): Interaction
    {
        if (! in_array($type, ['call', 'meeting', 'email'], true)) {
            throw new InvalidArgumentException('Unsupported interaction type.');
        }

        return DB::transaction(function () use ($subject, $actorId, $type, $occurredAt, $outcome, $note, $direction, $purpose, $contactId): Interaction {
            $contact = $contactId === null ? null : Contact::query()->findOrFail($contactId);
            $interaction = Interaction::query()->create($subject + [
                'contact_id' => $contactId,
                'user_id' => $actorId,
                'type' => $type,
                'direction' => $type === 'call' ? $direction : null,
                'purpose' => $type === 'call' ? $purpose : null,
                'occurred_at' => $occurredAt,
                'outcome' => filled($outcome) ? $outcome : null,
                'note' => filled($note) ? $note : null,
            ]);
            $this->activities->record(
                action: 'interaction.recorded',
                payload: [
                    'type' => $type,
                    'outcome' => $outcome,
                    'contact' => $contact === null ? null : ['id' => $contact->id, 'name' => $contact->full_name],
                ],
                actorId: $actorId,
                leadId: $subject['lead_id'] ?? null,
                dealId: $subject['deal_id'] ?? null,
                occurredAt: $occurredAt,
                defaultSource: 'user',
            );

            return $interaction;
        });
    }
}
