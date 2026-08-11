<?php

declare(strict_types=1);

namespace App\Domain\Document\DTOs;

final readonly class ChecklistResult
{
    /** @param list<int> $createdDocumentIds */
    public function __construct(public array $createdDocumentIds) {}
}
