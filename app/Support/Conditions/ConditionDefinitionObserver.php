<?php

declare(strict_types=1);

namespace App\Support\Conditions;

use App\Domain\Deal\Models\Transition;
use App\Domain\Program\Models\DocTemplate;

final readonly class ConditionDefinitionObserver
{
    public function __construct(private ConditionEvaluator $evaluator) {}

    public function saving(DocTemplate|Transition $model): void
    {
        $condition = $model->condition;

        if ($condition === null) {
            return;
        }

        ConditionDefinition::validate($condition, $this->evaluator);
    }
}
