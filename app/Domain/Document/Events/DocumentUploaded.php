<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentUploaded extends DomainEvent
{
    public function __construct(
        public string $documentId,
        public string $fileId,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'document.uploaded';
    }

    public function payload(): array
    {
        return [
            'document_id' => $this->documentId,
            'file_id' => $this->fileId,
        ];
    }
}
