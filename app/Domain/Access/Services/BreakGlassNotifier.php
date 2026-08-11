<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\BreakGlassNotification;

interface BreakGlassNotifier
{
    public function granted(BreakGlassNotification $notification): void;
}
