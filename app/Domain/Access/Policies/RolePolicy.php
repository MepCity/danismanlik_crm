<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class RolePolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'system.roles';

    protected const VIEW_PERMISSION = 'system.roles';
}
