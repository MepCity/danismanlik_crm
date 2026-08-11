<?php

declare(strict_types=1);

namespace App\Domain\Document\Exceptions;

use DomainException;

final class DocumentStatusRejected extends DomainException
{
    public static function transition(): self
    {
        return new self(trans('documents.errors.status_transition'));
    }

    public static function reason(): self
    {
        return new self(trans('documents.errors.reason_required'));
    }

    public static function forbidden(): self
    {
        return new self(trans('documents.errors.forbidden'));
    }
}
