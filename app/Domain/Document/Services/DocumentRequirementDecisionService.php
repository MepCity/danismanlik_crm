<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Document\Events\DocumentRequirementDecided;
use App\Domain\Document\Exceptions\DocumentOperationRejected;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Carbon;

final readonly class DocumentRequirementDecisionService
{
    public function __construct(
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
    ) {}

    public function decide(int $suggestionId, int $actorId, bool $accept): void
    {
        $this->transactions->run(ActorSource::User, $actorId, function () use ($suggestionId, $actorId, $accept): void {
            $suggestion = DocumentRequirementSuggestion::query()
                ->with('dealDocument')
                ->lockForUpdate()
                ->findOrFail($suggestionId);

            if ($suggestion->status !== 'pending') {
                throw DocumentOperationRejected::suggestionNotPending();
            }

            $decidedAt = Carbon::now();
            $suggestion->update([
                'status' => $accept ? 'accepted' : 'rejected',
                'decided_by' => $actorId,
                'decided_at' => $decidedAt,
            ]);

            if ($accept) {
                $suggestion->dealDocument->update(['status' => 'not_required']);
            }

            $document = $suggestion->dealDocument;
            $this->activities->record(
                action: 'document.requirement_decided',
                payload: [
                    'suggestion_id' => $suggestion->id,
                    'decision' => $accept ? 'accepted' : 'rejected',
                    'document' => ['id' => $document->id, 'name' => $document->name_snapshot],
                ],
                actorId: $actorId,
                dealDocumentId: $document->id,
                occurredAt: $decidedAt,
            );
            event(new DocumentRequirementDecided($suggestion->id, $document->id, $accept, $actorId));
        });
    }
}
