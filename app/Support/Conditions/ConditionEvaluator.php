<?php

declare(strict_types=1);

namespace App\Support\Conditions;

interface ConditionEvaluator
{
    /**
     * @param  array<string, mixed>  $condition
     */
    public function evaluate(array $condition, ConditionContext $context): ConditionResult;
}
