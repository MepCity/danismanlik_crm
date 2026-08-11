<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class LeadPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'lead.manage';

    protected const VIEW_PERMISSION = 'lead.manage';
}
