<?php

declare(strict_types=1);

namespace App\Support\Events;

use DateTimeImmutable;

abstract readonly class DomainEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(
        public ?string $actorId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    abstract public function name(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function payload(): array;
}
