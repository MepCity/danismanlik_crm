<?php

declare(strict_types=1);

namespace App\Support\Conditions\Exceptions;

final class UnresolvableConditionField extends ConditionConfigurationException
{
    public function __construct(private readonly string $field)
    {
        parent::__construct((string) trans($this->translationKey(), $this->translationParameters()));
    }

    public function translationKey(): string
    {
        return 'domain.errors.condition.unresolvable_field';
    }

    public function translationParameters(): array
    {
        return ['field' => $this->field];
    }
}
