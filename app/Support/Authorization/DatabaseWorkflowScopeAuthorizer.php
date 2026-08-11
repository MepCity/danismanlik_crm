<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Domain\Access\Services\WorkflowScopeAuthorizer;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Workflow\SubjectType;

final readonly class DatabaseWorkflowScopeAuthorizer implements WorkflowScopeAuthorizer
{
    public function __construct(private PolicyDecision $decisions) {}

    public function allows(int $actorId, string $permission, SubjectType $type, int $subjectId): bool
    {
        $actor = User::query()->find($actorId);
        $model = $type === SubjectType::Deal
            ? Deal::query()->findOrFail($subjectId)
            : Lead::query()->findOrFail($subjectId);

        return $actor !== null && $this->decisions->record($actor, $permission, $model);
    }
}
