<?php

declare(strict_types=1);

namespace App\Domain\Crm\DTOs;

final readonly class BulkEmailResult
{
    public function __construct(
        public int $queuedCount,
        public int $consentRejectedCount,
        public int $missingEmailCount,
        public int $duplicateCount,
    ) {}
}
