<?php

declare(strict_types=1);

use App\Domain\Deal\Services\StatusMachine;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Services\ChecklistGenerator;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Document\Services\ChecklistReevaluator;
use App\Domain\Document\Services\ChecklistReevaluatorContract;

it('resolves domain services through their contracts', function (): void {
    expect(app(StatusMachineContract::class))->toBeInstanceOf(StatusMachine::class)
        ->and(app(ChecklistGeneratorContract::class))->toBeInstanceOf(ChecklistGenerator::class)
        ->and(app(ChecklistReevaluatorContract::class))->toBeInstanceOf(ChecklistReevaluator::class);
});
