<?php

declare(strict_types=1);

namespace App\Domain\Crm\DTOs;

final readonly class LeadStatusTarget
{
    /** @param list<string> $requiredFields */
    public function __construct(
        public int $id,
        public array $requiredFields,
        public bool $convertsToDeal,
    ) {}
}
