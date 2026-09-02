<?php

declare(strict_types=1);

namespace App\Domain\Deal\Exceptions;

use App\Support\Exceptions\DomainException;

final class StatusTransitionRejected extends DomainException
{
    /**
     * @param  array<string, scalar|null>  $parameters
     */
    private function __construct(
        private readonly string $key,
        private readonly array $parameters = [],
    ) {
        parent::__construct((string) trans($key, $parameters));
    }

    public static function undefined(string $from, string $to): self
    {
        return new self('domain.errors.status.undefined_transition', compact('from', 'to'));
    }

    public static function inactive(string $from, string $to): self
    {
        return new self('domain.errors.status.inactive_transition', compact('from', 'to'));
    }

    public static function terminal(string $status): self
    {
        return new self('domain.errors.status.terminal', compact('status'));
    }

    public static function permission(string $permission): self
    {
        return new self('domain.errors.status.permission', compact('permission'));
    }

    /** @param list<string> $documents */
    public static function missingDocuments(array $documents): self
    {
        return new self('domain.errors.status.missing_documents', [
            'count' => count($documents),
            'documents' => implode(', ', $documents),
        ]);
    }

    /** @param list<string> $fields */
    public static function condition(array $fields): self
    {
        return new self('domain.errors.status.condition', [
            'fields' => implode(', ', $fields),
        ]);
    }

    public static function targetUnavailable(): self
    {
        return new self('domain.errors.status.target_unavailable');
    }

    public static function historyMissing(): self
    {
        return new self('domain.errors.status.history_missing');
    }

    public static function revisionMissing(): self
    {
        return new self('domain.errors.status.revision_missing');
    }

    public static function pathNotFound(string $from, string $to): self
    {
        return new self('domain.errors.status.path_not_found', compact('from', 'to'));
    }

    public static function targetNotFound(): self
    {
        return new self('domain.errors.status.target_not_found');
    }

    public static function transitionNotFound(string $from): self
    {
        return new self('domain.errors.status.transition_not_found', compact('from'));
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
