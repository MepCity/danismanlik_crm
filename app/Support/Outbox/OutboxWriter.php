<?php

declare(strict_types=1);

namespace App\Support\Outbox;

use App\Support\Events\DomainEvent;

interface OutboxWriter
{
    public function write(DomainEvent $event): void;
}
