<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

final class DealDocumentPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'document.upload';

    protected const VIEW_PERMISSION = 'document.view';
}
