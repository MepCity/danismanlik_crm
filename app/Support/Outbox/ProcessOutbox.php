<?php

declare(strict_types=1);

namespace App\Support\Outbox;

use App\Support\Queue\AutomationJob;

final class ProcessOutbox extends AutomationJob
{
    protected function execute(): void
    {
        // Olay işleme ve yeniden deneme davranışı sonraki domain paketlerindedir.
    }
}
