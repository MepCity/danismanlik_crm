<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function translationKey(): string;

    /**
     * @return array<string, scalar|null>
     */
    public function translationParameters(): array
    {
        return [];
    }
}
