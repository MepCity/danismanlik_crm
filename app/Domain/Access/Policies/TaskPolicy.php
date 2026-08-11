<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class TaskPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'task.manage';

    protected const VIEW_PERMISSION = 'task.manage';
}
