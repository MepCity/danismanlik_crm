<?php

declare(strict_types=1);

namespace App\Domain\Deal\Events;

use App\Support\Events\DomainEvent;

final readonly class DealAssigned extends DomainEvent
{
    public function __construct(
        public string $dealId,
        public string $projectManagerId,
        string $actorId,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'deal.assigned';
    }

    public function payload(): array
    {
        return [
            'deal_id' => $this->dealId,
            'project_manager_id' => $this->projectManagerId,
            'actor_id' => $this->actorId,
        ];
    }
}
