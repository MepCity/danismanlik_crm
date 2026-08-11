<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\BreakGlassNotification;
use App\Domain\Access\Enums\DataScope;
use App\Domain\Access\Exceptions\BreakGlassRejected;
use App\Domain\Access\Models\BreakGlassGrant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class BreakGlassService
{
    public function __construct(
        private EffectiveScopeResolver $scopes,
        private BreakGlassAuditRecorder $audit,
        private BreakGlassNotifier $notifications,
    ) {}

    public function grant(User $user, User $grantedBy, string $reason, ?Carbon $expiresAt): BreakGlassGrant
    {
        $reason = trim($reason);
        $maximumMinutes = (int) config('access.break_glass_max_minutes', 60);

        if (! $grantedBy->is_active || ! $grantedBy->can('access.break_glass.grant')) {
            throw BreakGlassRejected::forbidden();
        }

        if (! $user->is_active
            || ! $user->can('system.users')
            || $this->scopes->resolve($user) !== DataScope::None) {
            throw BreakGlassRejected::invalidTarget();
        }

        if ($reason === '') {
            throw BreakGlassRejected::invalidReason();
        }

        if ($expiresAt === null || ! $expiresAt->isFuture()) {
            throw BreakGlassRejected::invalidExpiry();
        }

        if ($expiresAt->greaterThan(now()->addMinutes($maximumMinutes))) {
            throw BreakGlassRejected::durationExceeded($maximumMinutes);
        }

        return DB::transaction(function () use ($user, $grantedBy, $reason, $expiresAt): BreakGlassGrant {
            $grant = BreakGlassGrant::query()->create([
                'user_id' => $user->id,
                'granted_by' => $grantedBy->id,
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);

            $this->audit->record('access.break_glass_granted', $grant, $grantedBy->id, [
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
            $this->notifications->granted(new BreakGlassNotification(
                userName: $user->name,
                reason: $grant->reason,
                expiresAt: $grant->expires_at,
            ));

            return $grant;
        });
    }

    public function revoke(BreakGlassGrant $grant, User $revokedBy): void
    {
        if (! $revokedBy->is_active || ! $revokedBy->can('access.break_glass.grant')) {
            throw BreakGlassRejected::forbidden();
        }

        if ($grant->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($grant, $revokedBy): void {
            $grant->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('access.break_glass_revoked', $grant, $revokedBy->id);
        });
    }
}
