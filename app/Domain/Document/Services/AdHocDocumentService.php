<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Events\AdHocDocumentCreated;
use App\Domain\Document\Models\DealDocument;
use App\Support\Audit\ActorSource;

final readonly class AdHocDocumentService
{
    public function __construct(
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
        private ChecklistDealGateway $deals,
    ) {}

    public function create(
        int $dealId,
        int $actorId,
        string $name,
        ?string $description,
        bool $required,
    ): DealDocument {
        return $this->transactions->run(ActorSource::User, $actorId, function () use ($dealId, $actorId, $name, $description, $required): DealDocument {
            $deal = $this->deals->lock($dealId);
            $document = DealDocument::query()->create([
                'deal_id' => $deal->id,
                'source_doc_template_id' => null,
                'source_program_version_id' => $deal->programVersionId,
                'name_snapshot' => $name,
                'description_snapshot' => $description,
                'required_snapshot' => $required,
                'condition_snapshot' => null,
                'condition_matches' => null,
                'status' => 'to_request',
            ]);

            $this->activities->record(
                action: 'document.ad_hoc_created',
                payload: [
                    'document' => ['id' => $document->id, 'name' => $document->name_snapshot],
                    'required' => $document->required_snapshot,
                ],
                actorId: $actorId,
                dealDocumentId: $document->id,
            );
            event(new AdHocDocumentCreated($deal->id, $document->id, $actorId));

            return $document;
        });
    }
}
