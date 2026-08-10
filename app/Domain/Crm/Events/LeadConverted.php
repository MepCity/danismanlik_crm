<?php

declare(strict_types=1);

namespace App\Domain\Crm\Events;

use App\Support\Events\DomainEvent;

final readonly class LeadConverted extends DomainEvent
{
    public function __construct(
        public string $leadId,
        public string $dealId,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'lead.converted';
    }

    public function payload(): array
    {
        return [
            'lead_id' => $this->leadId,
            'deal_id' => $this->dealId,
        ];
    }
}
