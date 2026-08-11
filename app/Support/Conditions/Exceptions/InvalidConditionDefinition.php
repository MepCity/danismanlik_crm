<?php

declare(strict_types=1);

namespace App\Support\Conditions\Exceptions;

final class InvalidConditionDefinition extends ConditionConfigurationException
{
    public function __construct()
    {
        parent::__construct((string) trans($this->translationKey()));
    }

    public function translationKey(): string
    {
        return 'domain.errors.condition.invalid_definition';
    }
}
