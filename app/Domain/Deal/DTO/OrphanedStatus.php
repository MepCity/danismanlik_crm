<?php

declare(strict_types=1);

namespace App\Domain\Deal\DTO;

use App\Support\Workflow\SubjectType;

final readonly class OrphanedStatus
{
    public function __construct(
        public int $statusId,
        public string $statusLabel,
        public SubjectType $subjectType,
        public int $subjectCount,
    ) {}
}
