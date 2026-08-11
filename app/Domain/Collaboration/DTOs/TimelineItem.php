<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\DTOs;

use Illuminate\Support\Carbon;

final readonly class TimelineItem
{
    public function __construct(
        public int $id,
        public string $type,
        public Carbon $occurredAt,
        public string $actor,
        public string $sentence,
    ) {}
}
