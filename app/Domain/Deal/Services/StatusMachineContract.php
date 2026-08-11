<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Support\Workflow\StatusTransition;

interface StatusMachineContract
{
    public function transition(StatusTransition $request): void;
}
