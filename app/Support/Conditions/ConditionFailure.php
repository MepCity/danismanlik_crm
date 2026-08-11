<?php

declare(strict_types=1);

namespace App\Support\Conditions;

final readonly class ConditionFailure
{
    /**
     * @param  list<mixed>  $rejectedValues
     */
    public function __construct(
        public string $field,
        public string $operator,
        public array $rejectedValues = [],
    ) {}
}
