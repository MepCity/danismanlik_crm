<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Collaboration\Services\DailyDigestService;
use Illuminate\Console\Command;

final class SendDailyDigestCommand extends Command
{
    protected $signature = 'collaboration:send-daily-digest';

    protected $description = 'Kullanıcıların günlük operasyon özetini kuyruğa alır';

    public function handle(DailyDigestService $service): int
    {
        $this->info(trans('collaboration.commands.digest', ['count' => $service->run()]));

        return self::SUCCESS;
    }
}
