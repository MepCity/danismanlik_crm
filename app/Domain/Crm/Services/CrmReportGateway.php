<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Lead;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class CrmReportGateway
{
    public function __construct(private ScopedQuery $scopes) {}

    /** @return Builder<Lead> */
    public function visibleLeads(User $user): Builder
    {
        return $this->scopes->apply(Lead::query(), $user, 'report.view');
    }
}
