<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentStatusChanged extends DomainEvent
{
    public function __construct(
        public int $documentId,
        public string $fromStatus,
        public string $toStatus,
        ?int $actorId = null,
    ) {
        parent::__construct($actorId === null ? null : (string) $actorId);
    }

    public function name(): string
    {
        return 'document.status_changed';
    }

    public function payload(): array
    {
        return ['document_id' => $this->documentId, 'from_status' => $this->fromStatus, 'to_status' => $this->toStatus];
    }
}
