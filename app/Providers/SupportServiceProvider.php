<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Audit\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActorContext::class, fn (): ActorContext => new ActorContext(DB::connection()));
    }
}
