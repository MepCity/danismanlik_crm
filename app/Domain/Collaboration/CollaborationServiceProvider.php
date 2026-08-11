<?php

declare(strict_types=1);

namespace App\Domain\Collaboration;

use App\Domain\Access\Services\BreakGlassNotifier;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\DatabaseActivityRecorder;
use App\Domain\Collaboration\Services\DatabaseBreakGlassNotifier;
use Illuminate\Support\ServiceProvider;

final class CollaborationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRecorder::class, DatabaseActivityRecorder::class);
        $this->app->bind(BreakGlassNotifier::class, DatabaseBreakGlassNotifier::class);
    }
}
