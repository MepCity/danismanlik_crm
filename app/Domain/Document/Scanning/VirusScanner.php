<?php

declare(strict_types=1);

namespace App\Domain\Document\Scanning;

interface VirusScanner
{
    public function scan(string $path): ScanResult;
}
