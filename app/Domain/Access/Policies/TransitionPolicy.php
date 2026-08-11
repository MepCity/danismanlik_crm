<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class TransitionPolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'system.settings';

    protected const VIEW_PERMISSION = 'system.settings';
}
