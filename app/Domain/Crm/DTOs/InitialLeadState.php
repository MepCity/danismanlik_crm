<?php

declare(strict_types=1);

namespace App\Domain\Crm\DTOs;

final readonly class InitialLeadState
{
    public function __construct(
        public int $statusId,
        public string $statusLabel,
        public int $workflowRevisionId,
    ) {}
}
