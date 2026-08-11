<?php

declare(strict_types=1);

namespace App\Domain\Deal\DTO;

final readonly class ChecklistDeal
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $programVersionId,
        public ?int $projectManagerId,
        public int $openedById,
        public ?string $requestedAmount,
    ) {}
}
