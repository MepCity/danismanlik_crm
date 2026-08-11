<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class ChecklistGenerated extends DomainEvent
{
    /** @param list<int> $documentIds */
    public function __construct(
        public int $dealId,
        public array $documentIds,
        ?string $actorId,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'deal.checklist_generated';
    }

    public function payload(): array
    {
        return ['deal_id' => $this->dealId, 'document_ids' => $this->documentIds];
    }
}
