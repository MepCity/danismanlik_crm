<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentsRequested extends DomainEvent
{
    /** @param list<int> $documentIds */
    public function __construct(public int $dealId, public array $documentIds, int $actorId)
    {
        parent::__construct((string) $actorId);
    }

    public function name(): string
    {
        return 'deal.documents_requested';
    }

    public function payload(): array
    {
        return ['deal_id' => $this->dealId, 'document_ids' => $this->documentIds];
    }
}
