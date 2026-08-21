<?php

declare(strict_types=1);

namespace App\Domain\Program\DTOs;

final readonly class ActiveProgramVersionData
{
    public function __construct(
        public int $id,
        public string $programName,
        public string $callPeriod,
        /** @var array<string, mixed>|null */
        public ?array $workflowSnapshot,
    ) {}

    public function label(): string
    {
        return $this->programName.' · '.$this->callPeriod;
    }
}
