<?php

declare(strict_types=1);

namespace App\Domain\Document\Scanning;

enum ScanResult: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
}
