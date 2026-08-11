<?php

declare(strict_types=1);

namespace App\Domain\Document\Exceptions;

use DomainException;

final class DocumentFileRejected extends DomainException
{
    public static function extension(): self
    {
        return new self(trans('documents.errors.extension'));
    }

    public static function mime(): self
    {
        return new self(trans('documents.errors.mime'));
    }

    public static function tooLarge(): self
    {
        return new self(trans('documents.errors.too_large'));
    }

    public static function duplicate(): self
    {
        return new self(trans('documents.errors.duplicate'));
    }

    public static function unavailable(): self
    {
        return new self(trans('documents.errors.unavailable'));
    }

    public static function forbidden(): self
    {
        return new self(trans('documents.errors.forbidden'));
    }

    public static function storage(): self
    {
        return new self(trans('documents.errors.storage'));
    }
}
