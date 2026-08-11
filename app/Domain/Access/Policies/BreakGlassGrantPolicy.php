<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class BreakGlassGrantPolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'access.break_glass.grant';

    protected const VIEW_PERMISSION = 'access.break_glass.grant';
}
