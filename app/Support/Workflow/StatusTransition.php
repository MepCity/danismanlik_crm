<?php

declare(strict_types=1);

namespace App\Support\Workflow;

final readonly class StatusTransition
{
    public function __construct(
        public SubjectType $subjectType,
        public int $subjectId,
        public int $targetStatusId,
        public int $actorId,
        public ?string $reason = null,
    ) {}
}
