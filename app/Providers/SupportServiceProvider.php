<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Audit\ActorHolder;
use App\Support\Audit\ApplyActorToTransaction;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ActorHolder::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionBeginning::class, ApplyActorToTransaction::class);
    }
}
