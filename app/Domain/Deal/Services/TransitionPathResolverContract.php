<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Transition;
use App\Support\Workflow\SubjectType;

interface TransitionPathResolverContract
{
    /**
     * Resolves the shortest, deterministic, authorized, and condition-satisfied
     * status path for a subject from its current status to a target status.
     *
     * @return list<int> List of status IDs representing the path [startStatusId, ..., targetStatusId]
     *
     * @throws StatusTransitionRejected
     */
    public function findShortestPath(
        SubjectType $subjectType,
        int $subjectId,
        int $targetStatusId,
        int $actorId,
    ): array;

    /**
     * Finds a deterministic, authorized, active transition from the subject's current status
     * matching the optional required permission and criteria.
     *
     * @throws StatusTransitionRejected
     */
    public function findDeterministicTransition(
        SubjectType $subjectType,
        int $subjectId,
        int $actorId,
        ?string $requiredPermission = null,
    ): Transition;
}
