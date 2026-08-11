<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Support\Workflow\SubjectType;
use Illuminate\Support\Carbon;

interface ActivityRecorder
{
    /**
     * @param  array{id: int, label: string}  $fromStatus
     * @param  array{id: int, label: string}  $toStatus
     */
    public function recordStatusChanged(
        SubjectType $subjectType,
        int $subjectId,
        int $actorId,
        array $fromStatus,
        array $toStatus,
        Carbon $occurredAt,
    ): void;
}
