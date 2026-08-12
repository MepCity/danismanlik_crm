<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RecordInteraction
{
    public function __construct(private readonly ActivityRecorder $activities) {}

    public function forLead(int $leadId, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        return DB::transaction(function () use ($leadId, $actorId, $type, $occurredAt, $duration, $outcome, $note): Interaction {
            if ($type === 'call') {
                $this->ensureMarketingCallAllowed($leadId, $occurredAt);
            }

            return $this->create(['lead_id' => $leadId], $actorId, $type, $occurredAt, $duration, $outcome, $note, 'outbound', 'marketing');
        });
    }

    public function forInboundLeadCall(int $leadId, int $actorId, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        return $this->create(['lead_id' => $leadId], $actorId, 'call', $occurredAt, $duration, $outcome, $note, 'inbound', 'marketing');
    }

    public function forDeal(int $dealId, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        return $this->create(['deal_id' => $dealId], $actorId, $type, $occurredAt, $duration, $outcome, $note, 'outbound', 'service');
    }

    /** @param array{lead_id?: int, deal_id?: int} $subject */
    private function create(array $subject, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note, string $direction, string $purpose): Interaction
    {
        if (! in_array($type, ['call', 'meeting', 'email'], true)) {
            throw new InvalidArgumentException('Unsupported interaction type.');
        }

        return DB::transaction(function () use ($subject, $actorId, $type, $occurredAt, $duration, $outcome, $note, $direction, $purpose): Interaction {
            $interaction = Interaction::query()->create($subject + [
                'user_id' => $actorId,
                'type' => $type,
                'direction' => $type === 'call' ? $direction : null,
                'purpose' => $type === 'call' ? $purpose : null,
                'occurred_at' => $occurredAt,
                'duration_minutes' => $duration,
                'outcome' => filled($outcome) ? $outcome : null,
                'note' => filled($note) ? $note : null,
            ]);
            $this->activities->record(
                action: 'interaction.recorded',
                payload: ['type' => $type, 'outcome' => $outcome, 'duration_minutes' => $duration],
                actorId: $actorId,
                leadId: $subject['lead_id'] ?? null,
                dealId: $subject['deal_id'] ?? null,
                occurredAt: $occurredAt,
                defaultSource: 'user',
            );

            return $interaction;
        });
    }

    private function ensureMarketingCallAllowed(int $leadId, Carbon $occurredAt): void
    {
        $companyId = Lead::query()->findOrFail($leadId)->company_id;
        $contact = Contact::query()
            ->where('company_id', $companyId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        $status = $contact instanceof Contact
            ? CommunicationConsent::query()
                ->where('contact_id', $contact->id)
                ->where('channel', 'call')
                ->where('purpose', 'marketing')
                ->where('effective_from', '<=', $occurredAt)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->value('status')
            : null;

        if ($status !== 'granted') {
            throw ValidationException::withMessages([
                'interaction' => __('marketing.validation.outbound_marketing_call_blocked'),
            ]);
        }
    }
}
