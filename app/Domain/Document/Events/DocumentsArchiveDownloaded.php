<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class DocumentsArchiveDownloaded extends DomainEvent
{
    /** @param list<int> $fileIds */
    public function __construct(public int $dealId, public array $fileIds, int $actorId)
    {
        parent::__construct((string) $actorId);
    }

    public function name(): string
    {
        return 'deal.documents_archive_downloaded';
    }

    public function payload(): array
    {
        return ['deal_id' => $this->dealId, 'file_ids' => $this->fileIds];
    }
}
