<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Document\Services\DocumentArchiveService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DealDocumentArchiveController
{
    public function __invoke(Request $request, int $deal, DocumentArchiveService $documents): StreamedResponse
    {
        return $documents->download($deal, (int) $request->user()->getAuthIdentifier());
    }
}
