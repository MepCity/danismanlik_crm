<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentDownloaded extends DomainEvent
{
    public function __construct(public int $fileId, int $actorId)
    {
        parent::__construct((string) $actorId);
    }

    public function name(): string
    {
        return 'document.downloaded';
    }

    public function payload(): array
    {
        return ['file_id' => $this->fileId];
    }
}
