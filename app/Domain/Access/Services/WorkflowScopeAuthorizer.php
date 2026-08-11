<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Support\Workflow\SubjectType;

interface WorkflowScopeAuthorizer
{
    public function allows(int $actorId, string $permission, SubjectType $type, int $subjectId): bool;
}
