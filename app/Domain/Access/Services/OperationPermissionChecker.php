<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Models\User;

final class OperationPermissionChecker
{
    public function __construct(private readonly ActiveBreakGlass $breakGlass) {}

    public function allows(int|User $actor, string $permission): bool
    {
        $user = $actor instanceof User ? $actor : User::query()->find($actor);

        if ($user === null || ! $user->is_active) {
            return false;
        }

        if ($user->can($permission)) {
            return true;
        }

        $emergencyPermissions = config('access.break_glass_permissions', []);

        if (! is_array($emergencyPermissions) || ! in_array($permission, $emergencyPermissions, true)) {
            return false;
        }

        $grant = $this->breakGlass->find($user);

        if ($grant === null) {
            $this->breakGlass->recordExpiredAttempt($user);
        }

        return $grant !== null;
    }
}
