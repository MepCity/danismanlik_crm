<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentRequirementDecided extends DomainEvent
{
    public function __construct(
        public int $suggestionId,
        public int $documentId,
        public bool $accepted,
        int $actorId,
    ) {
        parent::__construct((string) $actorId);
    }

    public function name(): string
    {
        return 'document.requirement_decided';
    }

    public function payload(): array
    {
        return [
            'suggestion_id' => $this->suggestionId,
            'document_id' => $this->documentId,
            'accepted' => $this->accepted,
        ];
    }
}
