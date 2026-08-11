<?php

declare(strict_types=1);

namespace App\Domain\Deal;

use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Observers\DealChecklistObserver;
use App\Domain\Deal\Services\OrphanTransitionInspector;
use App\Domain\Deal\Services\OrphanTransitionInspectorContract;
use App\Domain\Deal\Services\StatusMachine;
use App\Domain\Deal\Services\StatusMachineContract;
use Illuminate\Support\ServiceProvider;

final class DealServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StatusMachineContract::class, StatusMachine::class);
        $this->app->bind(OrphanTransitionInspectorContract::class, OrphanTransitionInspector::class);
    }

    public function boot(): void
    {
        Deal::observe(DealChecklistObserver::class);
    }
}
