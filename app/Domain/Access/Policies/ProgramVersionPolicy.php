<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class ProgramVersionPolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'program.manage';

    protected const VIEW_PERMISSION = 'program.view';
}
