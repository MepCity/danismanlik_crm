<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class AdHocDocumentCreated extends DomainEvent
{
    public function __construct(public int $dealId, public int $documentId, int $actorId)
    {
        parent::__construct((string) $actorId);
    }

    public function name(): string
    {
        return 'deal.ad_hoc_document_created';
    }

    public function payload(): array
    {
        return ['deal_id' => $this->dealId, 'document_id' => $this->documentId];
    }
}
