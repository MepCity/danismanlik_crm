<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Models\Interaction;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class RecordInteraction
{
    public function forLead(int $leadId, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        return $this->create(['lead_id' => $leadId], $actorId, $type, $occurredAt, $duration, $outcome, $note);
    }

    public function forDeal(int $dealId, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        return $this->create(['deal_id' => $dealId], $actorId, $type, $occurredAt, $duration, $outcome, $note);
    }

    /** @param array{lead_id?: int, deal_id?: int} $subject */
    private function create(array $subject, int $actorId, string $type, Carbon $occurredAt, ?int $duration, ?string $outcome, ?string $note): Interaction
    {
        if (! in_array($type, ['call', 'meeting', 'email'], true)) {
            throw new InvalidArgumentException('Unsupported interaction type.');
        }

        return Interaction::query()->create($subject + [
            'user_id' => $actorId,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'duration_minutes' => $duration,
            'outcome' => filled($outcome) ? $outcome : null,
            'note' => filled($note) ? $note : null,
        ]);
    }
}
