<?php

declare(strict_types=1);

namespace App\Domain\Crm\Events;

use App\Support\Events\DomainEvent;

final readonly class LeadStatusChanged extends DomainEvent
{
    public function __construct(
        public string $leadId,
        public string $fromStatusId,
        public string $toStatusId,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'lead.status_changed';
    }

    public function payload(): array
    {
        return [
            'lead_id' => $this->leadId,
            'from_status_id' => $this->fromStatusId,
            'to_status_id' => $this->toStatusId,
        ];
    }
}
