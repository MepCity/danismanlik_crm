<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\Document\Scanning\StubVirusScanner;
use App\Domain\Document\Scanning\VirusScanner;
use App\Domain\Document\Services\ChecklistGenerator;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Document\Services\ChecklistReevaluator;
use App\Domain\Document\Services\ChecklistReevaluatorContract;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class DocumentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChecklistGeneratorContract::class, ChecklistGenerator::class);
        $this->app->bind(ChecklistReevaluatorContract::class, ChecklistReevaluator::class);
        $this->app->bind(VirusScanner::class, function ($app): VirusScanner {
            $driver = (string) config('documents.scanner');

            return match ($driver) {
                'stub' => new StubVirusScanner($app->environment()),
                default => throw new InvalidArgumentException(trans('documents.errors.scanner_driver', ['driver' => $driver])),
            };
        });
    }
}
