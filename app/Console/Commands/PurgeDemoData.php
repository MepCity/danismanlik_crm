<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Demo\DemoDataPurger;
use Illuminate\Console\Command;
use RuntimeException;

final class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge {--confirm= : Onay metni}';

    protected $description = 'Kurgusal demo iş verisini ve demo hesaplarını temizler';

    public function handle(DemoDataPurger $purger): int
    {
        if ($this->option('confirm') !== 'DEMO VERİYİ TEMİZLE') {
            $this->components->error((string) trans('demo_cleanup.confirmation_required'));

            return self::INVALID;
        }

        try {
            $result = $purger->purge();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->components->info((string) trans('demo_cleanup.complete', $result));

        return self::SUCCESS;
    }
}
