<?php

declare(strict_types=1);

namespace App\Support\Conditions;

use App\Support\Conditions\Exceptions\InvalidConditionDefinition;
use App\Support\Conditions\Exceptions\UnknownConditionOperator;

final class JsonConditionEvaluator implements ConditionEvaluator
{
    public function evaluate(array $condition, ConditionContext $context): ConditionResult
    {
        $connector = $this->connector($condition);
        $rules = $condition[$connector];

        if (! is_array($rules) || ! array_is_list($rules) || $rules === []) {
            throw new InvalidConditionDefinition;
        }

        $results = array_map(function (mixed $rule) use ($context): ConditionResult {
            if (! is_array($rule)) {
                throw new InvalidConditionDefinition;
            }

            /** @var array<string, mixed> $rule */
            return $this->evaluateRule($rule, $context);
        }, $rules);

        $passed = $connector === 'all'
            ? ! in_array(false, array_column($results, 'passed'), true)
            : in_array(true, array_column($results, 'passed'), true);

        if ($passed) {
            return new ConditionResult(true);
        }

        return new ConditionResult(false, array_merge(...array_column($results, 'failures')));
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function connector(array $condition): string
    {
        $connectors = array_values(array_intersect(['all', 'any'], array_keys($condition)));

        if (count($connectors) !== 1 || count($condition) !== 1) {
            throw new InvalidConditionDefinition;
        }

        return $connectors[0];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function evaluateRule(array $rule, ConditionContext $context): ConditionResult
    {
        if (! isset($rule['field'], $rule['op']) || ! is_string($rule['field']) || ! is_string($rule['op']) || ! array_key_exists('value', $rule)) {
            throw new InvalidConditionDefinition;
        }

        $actual = $context->resolve($rule['field']);
        $expected = $rule['value'];

        return match ($rule['op']) {
            'in' => $this->evaluateIn($rule['field'], $actual, $expected),
            'gt' => $this->evaluateGreaterThan($rule['field'], $actual, $expected),
            'all_in' => $this->evaluateAllIn($rule['field'], $actual, $expected),
            default => throw new UnknownConditionOperator($rule['op']),
        };
    }

    private function evaluateIn(string $field, mixed $actual, mixed $expected): ConditionResult
    {
        if (! is_array($expected) || ! array_is_list($expected)) {
            throw new InvalidConditionDefinition;
        }

        $passed = in_array($actual, $expected, true);

        return new ConditionResult($passed, $passed ? [] : [new ConditionFailure($field, 'in', [$actual])]);
    }

    private function evaluateGreaterThan(string $field, mixed $actual, mixed $expected): ConditionResult
    {
        if (! is_numeric($actual) || ! is_numeric($expected)) {
            throw new InvalidConditionDefinition;
        }

        $passed = (float) $actual > (float) $expected;

        return new ConditionResult($passed, $passed ? [] : [new ConditionFailure($field, 'gt', [$actual])]);
    }

    private function evaluateAllIn(string $field, mixed $actual, mixed $expected): ConditionResult
    {
        if (! is_array($actual) || ! array_is_list($actual) || ! is_array($expected) || ! array_is_list($expected)) {
            throw new InvalidConditionDefinition;
        }

        $rejected = array_values(array_filter(
            $actual,
            static fn (mixed $value): bool => ! in_array($value, $expected, true),
        ));

        return new ConditionResult(
            $rejected === [],
            $rejected === [] ? [] : [new ConditionFailure($field, 'all_in', $rejected)],
        );
    }
}
