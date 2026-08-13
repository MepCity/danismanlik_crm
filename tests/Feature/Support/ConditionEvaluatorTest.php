<?php

declare(strict_types=1);

use App\Support\Conditions\ArrayConditionContext;
use App\Support\Conditions\Exceptions\UnknownConditionOperator;
use App\Support\Conditions\Exceptions\UnresolvableConditionField;
use App\Support\Conditions\JsonConditionEvaluator;

it('evaluates seeded condition DSL forms without database queries', function (): void {
    $context = new ArrayConditionContext([
        'company' => ['city' => 'Hatay'],
        'deal' => [
            'requested_amount' => '6000000.00',
            'required_documents' => [
                ['status' => 'accepted'],
                ['status' => 'not_required'],
            ],
        ],
    ]);
    $evaluator = new JsonConditionEvaluator;

    expect($evaluator->evaluate([
        'all' => [['op' => 'in', 'field' => 'company.city', 'value' => ['Adana', 'Hatay']]],
    ], $context)->passed)->toBeTrue()
        ->and($evaluator->evaluate([
            'all' => [['op' => 'gt', 'field' => 'deal.requested_amount', 'value' => 5000000]],
        ], $context)->passed)->toBeTrue()
        ->and($evaluator->evaluate([
            'all' => [[
                'op' => 'all_in',
                'field' => 'deal.required_documents.status',
                'value' => ['accepted', 'not_required'],
            ]],
        ], $context)->passed)->toBeTrue();
});

it('supports any and reports failed collection values', function (): void {
    $context = new ArrayConditionContext([
        'company' => ['city' => 'Ankara'],
        'deal' => ['required_documents' => [
            ['status' => 'accepted'],
            ['status' => 'requested'],
        ]],
    ]);
    $result = (new JsonConditionEvaluator)->evaluate([
        'any' => [
            ['op' => 'in', 'field' => 'company.city', 'value' => ['Hatay']],
            ['op' => 'all_in', 'field' => 'deal.required_documents.status', 'value' => ['accepted']],
        ],
    ], $context);

    expect($result->passed)->toBeFalse()
        ->and($result->failures)->toHaveCount(2)
        ->and($result->failures[1]->rejectedValues)->toBe(['requested']);
});

it('throws an explicit error for an unknown operator', function (): void {
    $evaluate = fn () => (new JsonConditionEvaluator)->evaluate([
        'all' => [['op' => 'contains_magic', 'field' => 'company.city', 'value' => 'Ankara']],
    ], new ArrayConditionContext(['company' => ['city' => 'Ankara']]));

    expect($evaluate)->toThrow(UnknownConditionOperator::class, 'contains_magic');
});

it('throws an explicit error for an unresolvable field path', function (): void {
    $evaluate = fn () => (new JsonConditionEvaluator)->evaluate([
        'all' => [['op' => 'in', 'field' => 'company.unknown', 'value' => ['Ankara']]],
    ], new ArrayConditionContext(['company' => ['city' => 'Ankara']]));

    expect($evaluate)->toThrow(UnresolvableConditionField::class, 'company.unknown');
});
