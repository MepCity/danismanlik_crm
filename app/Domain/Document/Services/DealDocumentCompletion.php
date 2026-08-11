<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Models\DealDocument;
use Illuminate\Support\Carbon;

final class DealDocumentCompletion
{
    public function __construct(private readonly ChecklistDealGateway $deals) {}

    public function documentReceived(int $dealId, Carbon $at): bool
    {
        $first = $this->deals->setFirstDocumentReceivedAtIfMissing($dealId, $at);
        $this->refresh($dealId, $at);

        return $first;
    }

    public function refresh(int $dealId, ?Carbon $at = null): void
    {
        $required = DealDocument::query()
            ->where('deal_id', $dealId)
            ->where('required_snapshot', true);
        $count = (clone $required)->count();
        $complete = $count > 0 && ! (clone $required)
            ->whereNotIn('status', ['accepted', 'not_required'])
            ->exists();

        $this->deals->setDocumentCompletion($dealId, $complete, $at ?? Carbon::now());
    }
}
