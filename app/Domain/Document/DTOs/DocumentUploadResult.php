<?php

declare(strict_types=1);

namespace App\Domain\Document\DTOs;

use App\Domain\Document\Models\File;

final readonly class DocumentUploadResult
{
    public function __construct(public File $file, public bool $firstDocumentReceived) {}
}
