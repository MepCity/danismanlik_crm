<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

interface StatusMachineContract
{
    public function transition(string $dealId, string $transitionId): void;
}
