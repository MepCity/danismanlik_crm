<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final readonly class PolicyDecision
{
    public function __construct(
        private OperationPermissionChecker $permissions,
        private ScopedQuery $queries,
    ) {}

    public function viewAny(User $user, string $permission, bool $configuration = false): bool
    {
        return $this->permissions->allows($user, $permission)
            && ($configuration || $this->queries->allowsAny($user, $permission));
    }

    public function create(User $user, string $permission, bool $configuration = false): bool
    {
        return $this->viewAny($user, $permission, $configuration);
    }

    public function record(User $user, string $permission, Model $model, bool $configuration = false): bool
    {
        return $this->permissions->allows($user, $permission)
            && ($configuration || $this->queries->contains($user, $model, $permission));
    }
}
