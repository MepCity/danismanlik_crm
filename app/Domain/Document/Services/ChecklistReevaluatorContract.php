<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\DTOs\ReevaluationResult;

interface ChecklistReevaluatorContract
{
    public function reevaluate(int $dealId): ReevaluationResult;
}
