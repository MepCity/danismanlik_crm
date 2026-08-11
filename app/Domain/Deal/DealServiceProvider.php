<?php

declare(strict_types=1);

namespace App\Domain\Deal;

use App\Domain\Deal\Services\StatusMachine;
use App\Domain\Deal\Services\StatusMachineContract;
use Illuminate\Support\ServiceProvider;

final class DealServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StatusMachineContract::class, StatusMachine::class);
    }
}
