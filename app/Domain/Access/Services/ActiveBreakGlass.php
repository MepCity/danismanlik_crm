<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Models\User;

final readonly class ActiveBreakGlass
{
    public function __construct(private BreakGlassAuditRecorder $audit) {}

    public function find(User $user): ?BreakGlassGrant
    {
        /** @var BreakGlassGrant|null $grant */
        $grant = BreakGlassGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        return $grant;
    }

    public function recordExpiredAttempt(User $user): void
    {
        /** @var BreakGlassGrant|null $grant */
        $grant = BreakGlassGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->latest('expires_at')
            ->first();

        if ($grant === null) {
            return;
        }

        $alreadyRecorded = RolePermissionHistory::query()
            ->where('change_type', 'access.break_glass_expired')
            ->whereRaw("new_value ->> 'grant_id' = ?", [(string) $grant->id])
            ->exists();

        if (! $alreadyRecorded) {
            $this->audit->record('access.break_glass_expired', $grant, $user->id);
        }
    }

    public function use(User $user, string $ability, ?string $resource = null): ?BreakGlassGrant
    {
        $grant = $this->find($user);

        if ($grant === null) {
            $this->recordExpiredAttempt($user);

            return null;
        }

        $this->audit->record('access.break_glass_used', $grant, $user->id, [
            'ability' => $ability,
            'resource' => $resource,
        ]);

        return $grant;
    }
}
