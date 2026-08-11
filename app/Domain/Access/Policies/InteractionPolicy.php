<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class InteractionPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'interaction.manage';

    protected const VIEW_PERMISSION = 'interaction.manage';
}
