<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Models\RolePermissionHistory;

final class BreakGlassAuditRecorder
{
    /** @param array<string, mixed> $details */
    public function record(string $event, BreakGlassGrant $grant, int $actorId, array $details = []): void
    {
        RolePermissionHistory::query()->create([
            'subject_type' => 'user',
            'subject_id' => $grant->user_id,
            'change_type' => $event,
            'new_value' => ['grant_id' => $grant->id, ...$details],
            'changed_by' => $actorId,
            'reason' => $grant->reason,
        ]);
    }
}
