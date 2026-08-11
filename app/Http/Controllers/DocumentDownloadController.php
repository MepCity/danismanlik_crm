<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Document\Services\DocumentAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController
{
    public function __invoke(Request $request, int $file, DocumentAccessService $documents): StreamedResponse
    {
        return $documents->download($file, (int) $request->user()->getAuthIdentifier());
    }
}
