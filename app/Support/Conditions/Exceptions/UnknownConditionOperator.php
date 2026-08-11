<?php

declare(strict_types=1);

namespace App\Support\Conditions\Exceptions;

final class UnknownConditionOperator extends ConditionConfigurationException
{
    public function __construct(private readonly string $operator)
    {
        parent::__construct((string) trans($this->translationKey(), $this->translationParameters()));
    }

    public function translationKey(): string
    {
        return 'domain.errors.condition.unknown_operator';
    }

    public function translationParameters(): array
    {
        return ['operator' => $this->operator];
    }
}
