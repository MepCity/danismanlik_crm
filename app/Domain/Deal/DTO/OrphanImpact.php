<?php

declare(strict_types=1);

namespace App\Domain\Deal\DTO;

final readonly class OrphanImpact
{
    /** @param list<OrphanedStatus> $statuses */
    public function __construct(public array $statuses) {}

    public function hasOrphans(): bool
    {
        return $this->statuses !== [];
    }

    public function subjectCount(): int
    {
        return array_sum(array_map(
            static fn (OrphanedStatus $status): int => $status->subjectCount,
            $this->statuses,
        ));
    }
}
