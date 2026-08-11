<?php

declare(strict_types=1);

namespace App\Domain\Document\Exceptions;

use App\Support\Exceptions\DomainException;

final class DocumentOperationRejected extends DomainException
{
    /** @param array<string, scalar|null> $parameters */
    private function __construct(
        private readonly string $key,
        private readonly array $parameters = [],
    ) {
        parent::__construct((string) trans($key, $parameters));
    }

    public static function suggestionNotPending(): self
    {
        return new self('domain.errors.document.suggestion_not_pending');
    }

    public static function invalidRequestStatus(string $document): self
    {
        return new self('domain.errors.document.request_invalid_status', compact('document'));
    }

    public static function mixedDeals(): self
    {
        return new self('domain.errors.document.request_mixed_deals');
    }

    public static function emptyRequest(): self
    {
        return new self('domain.errors.document.request_empty');
    }

    public static function missingRequestDocument(): self
    {
        return new self('domain.errors.document.request_missing');
    }

    public function translationKey(): string
    {
        return $this->key;
    }

    public function translationParameters(): array
    {
        return $this->parameters;
    }
}
