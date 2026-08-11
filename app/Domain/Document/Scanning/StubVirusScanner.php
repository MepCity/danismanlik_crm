<?php

declare(strict_types=1);

namespace App\Domain\Document\Scanning;

use RuntimeException;

final readonly class StubVirusScanner implements VirusScanner
{
    public function __construct(private string $environment) {}

    public function scan(string $path): ScanResult
    {
        if (! in_array($this->environment, ['local', 'testing'], true)) {
            throw new RuntimeException(trans('documents.errors.stub_production'));
        }

        return ScanResult::Clean;
    }
}
