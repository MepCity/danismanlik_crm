<?php

declare(strict_types=1);

namespace App\Domain\Document\DTOs;

final readonly class ReevaluationResult
{
    /**
     * @param  list<int>  $createdDocumentIds
     * @param  list<int>  $createdSuggestionIds
     */
    public function __construct(
        public array $createdDocumentIds,
        public array $createdSuggestionIds,
    ) {}
}
