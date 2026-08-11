<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\Enums\DataScope;
use App\Models\User;

final class EffectiveScopeResolver
{
    public function resolve(User $user): DataScope
    {
        if ($user->data_scope !== null) {
            return DataScope::from($user->data_scope);
        }

        $scopes = $user->roles()
            ->where('roles.is_active', true)
            ->pluck('default_scope')
            ->map(static fn (mixed $scope): DataScope => DataScope::from((string) $scope));

        return $scopes->sortBy(static fn (DataScope $scope): int => $scope->rank())
            ->first() ?? DataScope::None;
    }
}
