<?php

declare(strict_types=1);

namespace App\Support\Conditions;

interface ConditionContext
{
    public function resolve(string $field): mixed;
}
