<?php

declare(strict_types=1);

namespace App\Domain\Deal\Services;

use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class DealReportGateway
{
    public function __construct(private ScopedQuery $scopes) {}

    /** @return Builder<Deal> */
    public function visibleDeals(User $user): Builder
    {
        return $this->scopes->apply(Deal::query(), $user, 'report.view');
    }
}
