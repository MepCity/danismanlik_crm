<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class CompanyPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'company.manage';

    protected const VIEW_PERMISSION = 'company.manage';
}
