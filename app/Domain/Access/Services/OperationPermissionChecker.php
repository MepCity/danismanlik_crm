<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Models\User;

final class OperationPermissionChecker
{
    public function allows(int $actorId, string $permission): bool
    {
        $actor = User::query()->find($actorId);

        return $actor !== null && $actor->can($permission);
    }
}
