<?php

declare(strict_types=1);

namespace App\Support\Outbox;

use App\Support\Events\DomainEvent;
use LogicException;

final class PendingOutboxWriter implements OutboxWriter
{
    public function write(DomainEvent $event): void
    {
        throw new LogicException('WP-07');
    }
}
