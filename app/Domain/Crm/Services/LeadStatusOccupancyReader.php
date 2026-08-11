<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Lead;

final class LeadStatusOccupancyReader
{
    public function count(int $statusId): int
    {
        return Lead::query()->where('status_id', $statusId)->count();
    }
}
