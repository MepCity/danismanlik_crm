<?php

declare(strict_types=1);

namespace App\Support\Conditions;

final readonly class ConditionResult
{
    /**
     * @param  list<ConditionFailure>  $failures
     */
    public function __construct(
        public bool $passed,
        public array $failures = [],
    ) {}
}
