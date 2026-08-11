<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\DTOs\ChecklistResult;

interface ChecklistGeneratorContract
{
    public function generate(int $dealId, int $actorId): ChecklistResult;
}
