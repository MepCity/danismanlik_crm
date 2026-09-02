<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Access\Services\WorkflowScopeAuthorizer;
use App\Domain\Crm\Services\CompanyConditionDataReader;
use App\Domain\Deal\Models\Transition;
use App\Domain\Document\Services\RequiredDocumentDataReader;
use App\Support\Conditions\ArrayConditionContext;
use App\Support\Conditions\ConditionEvaluator;
use App\Support\Conditions\ConditionResult;
use App\Support\Workflow\SubjectType;
use App\Support\Workflow\WorkflowSubject;

final readonly class TransitionGuard
{
    public function __construct(
        private WorkflowScopeAuthorizer $authorization,
        private CompanyConditionDataReader $companies,
        private RequiredDocumentDataReader $documents,
        private ConditionEvaluator $conditions,
    ) {}

    public function isPermitted(Transition $transition, int $actorId, WorkflowSubject $subject): bool
    {
        if ($transition->required_permission === null) {
            return true;
        }

        return $this->authorization->allows(
            $actorId,
            $transition->required_permission,
            $subject->type,
            $subject->id,
        );
    }

    public function evaluateCondition(Transition $transition, WorkflowSubject $subject): ConditionResult
    {
        if ($transition->condition === null) {
            return new ConditionResult(true, []);
        }

        $requiredDocuments = $subject->type === SubjectType::Deal
            ? $this->documents->readForDeal($subject->id)
            : [];

        $context = new ArrayConditionContext([
            'company' => $this->companies->read($subject->companyId),
            'deal' => [
                'requested_amount' => $subject->requestedAmount,
                'required_documents' => $requiredDocuments,
            ],
        ]);

        return $this->conditions->evaluate($transition->condition, $context);
    }

    public function isConditionSatisfied(Transition $transition, WorkflowSubject $subject): bool
    {
        if ($transition->condition === null) {
            return true;
        }

        return $this->evaluateCondition($transition, $subject)->passed;
    }

    public function isEligible(Transition $transition, int $actorId, WorkflowSubject $subject): bool
    {
        return $transition->is_active
            && $this->isPermitted($transition, $actorId, $subject)
            && $this->isConditionSatisfied($transition, $subject);
    }
}
