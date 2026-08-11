<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\Activity;
use App\Support\Audit\ActorHolder;
use App\Support\Workflow\SubjectType;
use Illuminate\Support\Carbon;

final readonly class DatabaseActivityRecorder implements ActivityRecorder
{
    public function __construct(private ActorHolder $actors) {}

    public function record(
        string $action,
        array $payload,
        ?int $actorId = null,
        ?int $leadId = null,
        ?int $dealId = null,
        ?int $dealDocumentId = null,
        ?Carbon $occurredAt = null,
    ): void {
        $actor = $this->actors->current();

        Activity::query()->create([
            'actor_id' => $actorId,
            'lead_id' => $leadId,
            'deal_id' => $dealId,
            'deal_document_id' => $dealDocumentId,
            'action' => $action,
            'payload' => $payload,
            'source' => $actor?->source->value ?? 'system',
            'ip_address' => $actor?->clientIp,
            'created_at' => $occurredAt ?? Carbon::now(),
        ]);
    }

    public function recordStatusChanged(
        SubjectType $subjectType,
        int $subjectId,
        int $actorId,
        array $fromStatus,
        array $toStatus,
        Carbon $occurredAt,
    ): void {
        $this->record(
            action: $subjectType->value.'.status_changed',
            payload: [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ],
            actorId: $actorId,
            leadId: $subjectType === SubjectType::Lead ? $subjectId : null,
            dealId: $subjectType === SubjectType::Deal ? $subjectId : null,
            occurredAt: $occurredAt,
        );
    }
}
