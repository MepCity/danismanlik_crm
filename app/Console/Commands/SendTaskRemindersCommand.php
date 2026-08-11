<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Collaboration\Services\DueTaskReminder;
use Illuminate\Console\Command;

final class SendTaskRemindersCommand extends Command
{
    protected $signature = 'collaboration:send-reminders';

    protected $description = 'Vakti gelen görev hatırlatmalarını üretir';

    public function handle(DueTaskReminder $service): int
    {
        $this->info(trans('collaboration.commands.reminders', ['count' => $service->run()]));

        return self::SUCCESS;
    }
}
