<?php

declare(strict_types=1);

namespace App\Domain\Program\DTOs;

final readonly class DocumentTemplateData
{
    /** @param array<string, mixed>|null $condition */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $required,
        public ?array $condition,
    ) {}
}
