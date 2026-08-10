<?php

declare(strict_types=1);

namespace App\Support\Queue;

use App\Support\Audit\Actor;
use App\Support\Audit\ActorHolder;
use App\Support\Audit\ActorSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class AutomationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    final public function handle(ActorHolder $actorHolder): void
    {
        $actorHolder->runWith(
            new Actor(source: ActorSource::Automation),
            fn () => $this->execute(),
        );
    }

    abstract protected function execute(): void;
}
