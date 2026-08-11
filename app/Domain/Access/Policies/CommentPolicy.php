<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class CommentPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'collaboration.manage';

    protected const VIEW_PERMISSION = 'collaboration.manage';
}
