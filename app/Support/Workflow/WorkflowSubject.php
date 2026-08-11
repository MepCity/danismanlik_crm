<?php

declare(strict_types=1);

namespace App\Support\Workflow;

final readonly class WorkflowSubject
{
    public function __construct(
        public SubjectType $type,
        public int $id,
        public int $companyId,
        public int $statusId,
        public ?string $requestedAmount = null,
    ) {}
}
