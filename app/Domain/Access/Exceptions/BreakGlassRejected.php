<?php

declare(strict_types=1);

namespace App\Domain\Access\Exceptions;

use DomainException;

final class BreakGlassRejected extends DomainException
{
    public static function forbidden(): self
    {
        return new self((string) trans('access.break_glass.forbidden'));
    }

    public static function invalidReason(): self
    {
        return new self((string) trans('access.break_glass.reason_required'));
    }

    public static function invalidExpiry(): self
    {
        return new self((string) trans('access.break_glass.expiry_required'));
    }

    public static function durationExceeded(int $minutes): self
    {
        return new self((string) trans('access.break_glass.duration_exceeded', ['minutes' => $minutes]));
    }

    public static function invalidTarget(): self
    {
        return new self((string) trans('access.break_glass.invalid_target'));
    }
}
