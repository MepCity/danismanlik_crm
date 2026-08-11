<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Document\Events\DocumentStatusChanged;
use App\Domain\Document\Exceptions\DocumentStatusRejected;
use App\Domain\Document\Models\DealDocument;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Carbon;

final readonly class DocumentStatusService
{
    public function __construct(
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
        private DealDocumentCompletion $completion,
        private OperationPermissionChecker $permissions,
    ) {}

    public function startReview(int $documentId, int $actorId): DealDocument
    {
        return $this->change($documentId, 'under_review', null, $actorId, ActorSource::User, ['uploaded']);
    }

    public function decide(int $documentId, string $target, ?string $reason, int $actorId): DealDocument
    {
        if (! in_array($target, ['accepted', 'rejected', 'new_version_expected'], true)) {
            throw DocumentStatusRejected::transition();
        }

        if (in_array($target, ['rejected', 'new_version_expected'], true) && trim((string) $reason) === '') {
            throw DocumentStatusRejected::reason();
        }

        return $this->change($documentId, $target, $reason, $actorId, ActorSource::User, ['under_review']);
    }

    /** @param list<string> $allowedFrom */
    public function change(
        int $documentId,
        string $target,
        ?string $reason,
        ?int $actorId,
        ActorSource $source,
        array $allowedFrom,
    ): DealDocument {
        if ($source === ActorSource::User
            && ($actorId === null || ! $this->permissions->allows($actorId, 'document.approve'))) {
            throw DocumentStatusRejected::forbidden();
        }

        return $this->transactions->run($source, $actorId, function () use ($documentId, $target, $reason, $actorId, $source, $allowedFrom): DealDocument {
            $document = DealDocument::query()->with('deal')->lockForUpdate()->findOrFail($documentId);

            if (! in_array($document->status, $allowedFrom, true)) {
                throw DocumentStatusRejected::transition();
            }

            $from = $document->status;
            $document->update(['status' => $target, 'notes' => $reason ?? $document->notes]);
            $this->completion->refresh($document->deal_id, Carbon::now());
            $this->activities->record(
                'document.status_changed',
                ['from_status' => $from, 'to_status' => $target, 'reason' => $reason],
                $actorId,
                dealDocumentId: $document->id,
                defaultSource: $source->value,
            );
            event(new DocumentStatusChanged($document->id, $from, $target, $actorId));

            return $document->refresh();
        });
    }
}
