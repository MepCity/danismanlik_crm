<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class TeamPolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'system.users';

    protected const VIEW_PERMISSION = 'system.users';
}
