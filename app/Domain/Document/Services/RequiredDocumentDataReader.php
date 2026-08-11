<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\Models\DealDocument;

final class RequiredDocumentDataReader
{
    /** @return list<array{name: string, status: string}> */
    public function readForDeal(int $dealId): array
    {
        return DealDocument::query()
            ->where('deal_id', $dealId)
            ->where('required_snapshot', true)
            ->orderBy('id')
            ->get(['name_snapshot', 'status'])
            ->map(static fn (DealDocument $document): array => [
                'name' => $document->name_snapshot,
                'status' => $document->status,
            ])
            ->all();
    }
}
