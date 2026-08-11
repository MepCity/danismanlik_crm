<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class DealPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'deal.transition';

    protected const VIEW_PERMISSION = 'deal.view';
}
