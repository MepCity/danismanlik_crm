<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class EmailTemplatePolicy extends ScopedPolicy
{
    protected const CONFIGURATION = true;

    protected const MUTATE_PERMISSION = 'system.templates';

    protected const VIEW_PERMISSION = 'system.templates';
}
