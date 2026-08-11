<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\DTO\OrphanImpact;

interface OrphanTransitionInspectorContract
{
    public function beforeTransitionDeactivation(int $transitionId): OrphanImpact;

    public function beforeStatusDeactivation(int $statusId): OrphanImpact;
}
