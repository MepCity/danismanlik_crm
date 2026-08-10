<?php

declare(strict_types=1);

use App\Domain\Deal\Services\StatusMachine;
use App\Domain\Deal\Services\StatusMachineContract;

it('resolves domain services through their contracts', function (): void {
    expect(app(StatusMachineContract::class))->toBeInstanceOf(StatusMachine::class);
});
