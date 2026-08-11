<?php

declare(strict_types=1);

namespace App\Support\Audit;

final readonly class Actor
{
    public function __construct(
        public ActorSource $source,
        public ?string $id = null,
        public ?string $sessionId = null,
        public ?string $clientIp = null,
    ) {}
}
