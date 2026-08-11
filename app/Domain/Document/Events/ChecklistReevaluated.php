<?php

declare(strict_types=1);

namespace App\Domain\Document\Events;

use App\Support\Events\DomainEvent;

final readonly class ChecklistReevaluated extends DomainEvent
{
    /**
     * @param  list<int>  $documentIds
     * @param  list<int>  $suggestionIds
     */
    public function __construct(
        public int $dealId,
        public array $documentIds,
        public array $suggestionIds,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'deal.checklist_reevaluated';
    }

    public function payload(): array
    {
        return [
            'deal_id' => $this->dealId,
            'document_ids' => $this->documentIds,
            'suggestion_ids' => $this->suggestionIds,
        ];
    }
}
