<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Models\User;

final class OperationPermissionChecker
{
    public function allows(int|User $actor, string $permission): bool
    {
        $user = $actor instanceof User ? $actor : User::query()->find($actor);

        if ($user === null || ! $user->is_active) {
            return false;
        }

        return $user->can($permission);
    }
}
