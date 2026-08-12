<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\Models\DealDocument;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Illuminate\Database\Eloquent\Builder;

final readonly class DocumentReportGateway
{
    public function __construct(private ScopedQuery $scopes) {}

    /** @return Builder<DealDocument> */
    public function visibleDocuments(User $user): Builder
    {
        return $this->scopes->apply(DealDocument::query(), $user, 'report.view');
    }
}
