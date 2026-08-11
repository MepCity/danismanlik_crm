<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Events\DocumentsRequested;
use App\Domain\Document\Exceptions\DocumentOperationRejected;
use App\Domain\Document\Models\DealDocument;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Carbon;

final readonly class DocumentRequestService
{
    public function __construct(
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
        private ChecklistDealGateway $deals,
    ) {}

    /** @param list<int> $documentIds */
    public function markRequested(array $documentIds, int $actorId): void
    {
        if ($documentIds === []) {
            throw DocumentOperationRejected::emptyRequest();
        }

        $this->transactions->run(ActorSource::User, $actorId, function () use ($documentIds, $actorId): void {
            $documents = DealDocument::query()
                ->whereKey($documentIds)
                ->lockForUpdate()
                ->get();

            if ($documents->count() !== count(array_unique($documentIds))) {
                throw DocumentOperationRejected::missingRequestDocument();
            }

            if ($documents->pluck('deal_id')->unique()->count() !== 1) {
                throw DocumentOperationRejected::mixedDeals();
            }

            foreach ($documents as $document) {
                if ($document->status !== 'to_request') {
                    throw DocumentOperationRejected::invalidRequestStatus($document->name_snapshot);
                }
            }

            $requestedAt = Carbon::now();
            foreach ($documents as $document) {
                $document->update(['status' => 'requested', 'requested_at' => $requestedAt]);
            }

            $dealId = (int) $documents->firstOrFail()->deal_id;
            $this->deals->setDocumentRequestedAtIfMissing($dealId, $requestedAt);

            $ids = $documents->modelKeys();
            $this->activities->record(
                action: 'deal.documents_requested',
                payload: ['document_ids' => $ids, 'document_count' => count($ids)],
                actorId: $actorId,
                dealId: $dealId,
                occurredAt: $requestedAt,
            );
            event(new DocumentsRequested($dealId, $ids, $actorId));
        });
    }
}
