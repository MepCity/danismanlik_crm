<?php

declare(strict_types=1);

namespace App\Support\Outbox;

use App\Support\Queue\AutomationJob;

final class ProcessOutbox extends AutomationJob
{
    protected function execute(): void
    {
        // Outbox tablosu ve işleme davranışı WP-07'de eklenecek.
    }
}
