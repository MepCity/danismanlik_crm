<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\Document\Services\ChecklistGenerator;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Document\Services\ChecklistReevaluator;
use App\Domain\Document\Services\ChecklistReevaluatorContract;
use Illuminate\Support\ServiceProvider;

final class DocumentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChecklistGeneratorContract::class, ChecklistGenerator::class);
        $this->app->bind(ChecklistReevaluatorContract::class, ChecklistReevaluator::class);
    }
}
