<?php

declare(strict_types=1);

namespace App\Domain\Collaboration;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\DatabaseActivityRecorder;
use Illuminate\Support\ServiceProvider;

final class CollaborationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRecorder::class, DatabaseActivityRecorder::class);
    }
}
