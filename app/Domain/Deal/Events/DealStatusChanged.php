<?php

declare(strict_types=1);

namespace App\Domain\Deal\Events;

use App\Support\Events\DomainEvent;

final readonly class DealStatusChanged extends DomainEvent
{
    public function __construct(
        public string $dealId,
        public string $fromStatusId,
        public string $toStatusId,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'deal.status_changed';
    }

    public function payload(): array
    {
        return [
            'deal_id' => $this->dealId,
            'from_status_id' => $this->fromStatusId,
            'to_status_id' => $this->toStatusId,
        ];
    }
}
