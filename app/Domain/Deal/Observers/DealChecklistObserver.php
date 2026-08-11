<?php

declare(strict_types=1);

namespace App\Domain\Deal\Observers;

use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Services\ChecklistReevaluatorContract;

final readonly class DealChecklistObserver
{
    public function __construct(private ChecklistReevaluatorContract $reevaluator) {}

    public function updated(Deal $deal): void
    {
        if ($deal->wasChanged('requested_amount')) {
            $this->reevaluator->reevaluate($deal->id);
        }
    }
}
