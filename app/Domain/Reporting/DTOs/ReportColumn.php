<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

final readonly class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $numeric = false,
    ) {}
}
