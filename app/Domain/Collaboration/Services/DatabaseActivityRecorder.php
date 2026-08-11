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

    public function recordStatusChanged(
        SubjectType $subjectType,
        int $subjectId,
        int $actorId,
        array $fromStatus,
        array $toStatus,
        Carbon $occurredAt,
    ): void {
        $actor = $this->actors->current();

        Activity::query()->create([
            'actor_id' => $actorId,
            $subjectType->value.'_id' => $subjectId,
            'action' => $subjectType->value.'.status_changed',
            'payload' => [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ],
            'source' => $actor?->source->value ?? 'user',
            'ip_address' => $actor?->clientIp,
            'created_at' => $occurredAt,
        ]);
    }
}
