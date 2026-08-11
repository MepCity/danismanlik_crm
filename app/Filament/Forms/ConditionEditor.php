<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Conditions\ConditionDefinition;
use App\Support\Conditions\ConditionEvaluator;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

final class ConditionEditor
{
    /** @return array<int, mixed> */
    public static function schema(): array
    {
        return [
            Repeater::make('condition_rules')
                ->label(__('management.fields.condition'))
                ->schema([
                    Select::make('field')
                        ->label(__('management.fields.condition_field'))
                        ->options(ConditionDefinition::fieldOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(static function (Set $set): void {
                            $set('op', null);
                            $set('list_value', []);
                            $set('numeric_value', null);
                        }),
                    Select::make('op')
                        ->label(__('management.fields.condition_operator'))
                        ->options(static fn (Get $get): array => ConditionDefinition::operatorOptions($get('field')))
                        ->required()
                        ->live(),
                    Select::make('list_value')
                        ->label(__('management.fields.condition_value'))
                        ->options(static fn (Get $get): array => match ($get('field')) {
                            'company.city' => __('management.conditions.values.cities'),
                            'deal.required_documents.status' => __('management.conditions.values.document_statuses'),
                            default => [],
                        })
                        ->multiple()
                        ->searchable()
                        ->required(static fn (Get $get): bool => $get('field') !== 'deal.requested_amount')
                        ->visible(static fn (Get $get): bool => $get('field') !== 'deal.requested_amount'),
                    TextInput::make('numeric_value')
                        ->label(__('management.fields.condition_value'))
                        ->numeric()
                        ->minValue(0)
                        ->required(static fn (Get $get): bool => $get('field') === 'deal.requested_amount')
                        ->visible(static fn (Get $get): bool => $get('field') === 'deal.requested_amount'),
                ])
                ->columns(4)
                ->addActionLabel(__('management.conditions.add_rule'))
                ->defaultItems(0)
                ->live()
                ->rule(static function (): Closure {
                    return static function (string $attribute, mixed $value, Closure $fail): void {
                        try {
                            $condition = self::conditionFromState(is_array($value) ? $value : []);
                            if ($condition !== null) {
                                ConditionDefinition::validate($condition, app(ConditionEvaluator::class));
                            }
                        } catch (Throwable) {
                            $fail(__('management.validation.invalid_condition'));
                        }
                    };
                }),
            Placeholder::make('condition_preview')
                ->label(__('management.fields.condition_preview'))
                ->content(static fn (Get $get): string => ConditionDefinition::preview(
                    self::conditionFromState((array) $get('condition_rules')),
                )),
        ];
    }

    /** @param array<int|string, mixed> $state
     * @return array{all: list<array<string, mixed>>}|null
     */
    public static function conditionFromState(array $state): ?array
    {
        $rules = [];

        foreach (array_values($state) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $field = $item['field'] ?? null;
            $rules[] = [
                'field' => $field,
                'op' => $item['op'] ?? null,
                'value' => $field === 'deal.requested_amount'
                    ? ($item['numeric_value'] ?? null)
                    : array_values((array) ($item['list_value'] ?? [])),
            ];
        }

        return ConditionDefinition::fromRules($rules);
    }

    /** @param array<string, mixed>|null $condition
     * @return list<array<string, mixed>>
     */
    public static function stateFromCondition(?array $condition): array
    {
        return array_map(static function (array $rule): array {
            $field = $rule['field'] ?? null;

            return [
                'field' => $field,
                'op' => $rule['op'] ?? null,
                'list_value' => $field === 'deal.requested_amount' ? [] : (array) ($rule['value'] ?? []),
                'numeric_value' => $field === 'deal.requested_amount' ? ($rule['value'] ?? null) : null,
            ];
        }, ConditionDefinition::toRules($condition));
    }
}
