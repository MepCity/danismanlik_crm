<?php

declare(strict_types=1);

namespace App\Support\Conditions;

use App\Support\Conditions\Exceptions\UnresolvableConditionField;

final readonly class ArrayConditionContext implements ConditionContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data) {}

    public function resolve(string $field): mixed
    {
        if ($field === '') {
            throw new UnresolvableConditionField($field);
        }

        return $this->resolveSegments($this->data, explode('.', $field), $field);
    }

    /**
     * @param  list<string>  $segments
     */
    private function resolveSegments(mixed $current, array $segments, string $field): mixed
    {
        if ($segments === []) {
            return $current;
        }

        $segment = array_shift($segments);

        if (is_array($current) && array_is_list($current)) {
            return array_map(
                fn (mixed $item): mixed => $this->resolveSegments($item, [$segment, ...$segments], $field),
                $current,
            );
        }

        if (! is_array($current) || ! array_key_exists($segment, $current)) {
            throw new UnresolvableConditionField($field);
        }

        return $this->resolveSegments($current[$segment], $segments, $field);
    }
}
