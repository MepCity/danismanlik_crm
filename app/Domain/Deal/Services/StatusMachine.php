<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use LogicException;

final class StatusMachine implements StatusMachineContract
{
    public function transition(string $dealId, string $transitionId): void
    {
        throw new LogicException('WP-09');
    }
}
