<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Support\Workflow\SubjectType;
use Illuminate\Support\Carbon;

interface ActivityRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(
        string $action,
        array $payload,
        ?int $actorId = null,
        ?int $leadId = null,
        ?int $dealId = null,
        ?int $dealDocumentId = null,
        ?Carbon $occurredAt = null,
        ?string $defaultSource = null,
        ?int $companyId = null,
        ?int $programId = null,
    ): void;

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
