<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Deal\DTO\OrphanImpact;

final class OrphanImpactPresenter
{
    public function describe(OrphanImpact $impact): string
    {
        return $impact->hasOrphans()
            ? collect($impact->statuses)->map(fn ($orphan): string => __('management.messages.orphan_warning', [
                'count' => $orphan->subjectCount,
                'status' => $orphan->statusLabel,
            ]))->implode(' ')
            : __('management.actions.deactivate_help');
    }
}
