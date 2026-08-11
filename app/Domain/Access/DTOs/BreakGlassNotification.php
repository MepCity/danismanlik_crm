<?php

declare(strict_types=1);

namespace App\Domain\Access\DTOs;

use Illuminate\Support\Carbon;

final readonly class BreakGlassNotification
{
    public function __construct(
        public string $userName,
        public string $reason,
        public Carbon $expiresAt,
    ) {}
}
