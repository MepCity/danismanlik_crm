<?php

declare(strict_types=1);

namespace App\Support\Outbox;

use App\Support\Events\DomainEvent;
use App\Support\Outbox\Models\OutboxMessage;

final class DatabaseOutboxWriter implements OutboxWriter
{
    public function write(DomainEvent $event): void
    {
        OutboxMessage::query()->create([
            'event_name' => $event->name(),
            'payload' => $event->payload(),
            'actor_id' => $event->actorId,
            'available_at' => $event->occurredAt,
            'created_at' => $event->occurredAt,
        ]);
    }
}
