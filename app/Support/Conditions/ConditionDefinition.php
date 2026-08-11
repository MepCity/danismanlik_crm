<?php

declare(strict_types=1);

namespace App\Support\Conditions;

use App\Support\Conditions\Exceptions\InvalidConditionDefinition;

final class ConditionDefinition
{
    /** @return array<string, array{label: string, operators: list<string>, value_type: string}> */
    public static function fields(): array
    {
        return [
            'company.city' => [
                'label' => __('management.conditions.fields.company_city'),
                'operators' => ['in'],
                'value_type' => 'cities',
            ],
            'deal.requested_amount' => [
                'label' => __('management.conditions.fields.requested_amount'),
                'operators' => ['gt'],
                'value_type' => 'number',
            ],
            'deal.required_documents.status' => [
                'label' => __('management.conditions.fields.document_status'),
                'operators' => ['all_in'],
                'value_type' => 'document_statuses',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function fieldOptions(): array
    {
        return collect(self::fields())->mapWithKeys(
            static fn (array $definition, string $field): array => [$field => $definition['label']],
        )->all();
    }

    /** @return array<string, string> */
    public static function operatorOptions(?string $field = null): array
    {
        $operators = $field !== null && isset(self::fields()[$field])
            ? self::fields()[$field]['operators']
            : ['in', 'gt', 'all_in'];

        return collect($operators)->mapWithKeys(
            static fn (string $operator): array => [$operator => __('management.conditions.operators.'.$operator)],
        )->all();
    }

    /** @param list<array<string, mixed>> $rules
     * @return array{all: list<array<string, mixed>>}|null
     */
    public static function fromRules(array $rules): ?array
    {
        if ($rules === []) {
            return null;
        }

        return ['all' => $rules];
    }

    /** @param array<string, mixed>|null $condition
     * @return list<array<string, mixed>>
     */
    public static function toRules(?array $condition): array
    {
        $rules = $condition['all'] ?? [];

        return is_array($rules) && array_is_list($rules) ? $rules : [];
    }

    /** @param array<string, mixed> $condition */
    public static function validate(array $condition, ConditionEvaluator $evaluator): void
    {
        foreach (self::toRules($condition) as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['op'] ?? null;

            if (! is_string($field) || ! isset(self::fields()[$field])) {
                throw new InvalidConditionDefinition;
            }

            if (! is_string($operator) || ! in_array($operator, self::fields()[$field]['operators'], true)) {
                throw new InvalidConditionDefinition;
            }
        }

        $evaluator->evaluate($condition, new ArrayConditionContext([
            'company' => ['city' => '06'],
            'deal' => [
                'requested_amount' => 1_000_000,
                'required_documents' => [['status' => 'accepted']],
            ],
        ]));
    }

    /** @param array<string, mixed>|null $condition */
    public static function preview(?array $condition): string
    {
        $sentences = [];

        foreach (self::toRules($condition) as $rule) {
            $field = is_string($rule['field'] ?? null) ? $rule['field'] : '';
            $operator = is_string($rule['op'] ?? null) ? $rule['op'] : '';
            $value = $rule['value'] ?? null;

            if (! isset(self::fields()[$field]) || ! in_array($operator, self::fields()[$field]['operators'], true)) {
                continue;
            }

            $formatted = self::formatValue($field, $value);
            $translationField = str_replace('.', '_', $field);
            $sentences[] = __('management.conditions.preview.'.$translationField.'.'.$operator, ['value' => $formatted]);
        }

        return $sentences === []
            ? __('management.conditions.preview.none')
            : implode(' '.mb_strtolower(__('management.conditions.preview.and')).' ', $sentences);
    }

    private static function formatValue(string $field, mixed $value): string
    {
        if ($field === 'deal.requested_amount' && is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.').' ₺';
        }

        $values = is_array($value) ? $value : [$value];
        if ($field === 'company.city') {
            return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $values));
        }

        $labels = match ($field) {
            'deal.required_documents.status' => __('management.conditions.values.document_statuses'),
            default => [],
        };

        return implode(', ', array_map(
            static fn (mixed $item): string => is_array($labels) && isset($labels[(string) $item])
                ? (string) $labels[(string) $item]
                : (string) $item,
            $values,
        ));
    }
}
