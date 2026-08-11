<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Deal\DTO\ChecklistDeal;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\DTOs\ChecklistResult;
use App\Domain\Document\Events\ChecklistGenerated;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\DTOs\DocumentTemplateData;
use App\Domain\Program\Services\DocumentTemplateReader;
use App\Support\Audit\ActorSource;
use App\Support\Conditions\ConditionEvaluator;

final readonly class ChecklistGenerator implements ChecklistGeneratorContract
{
    public function __construct(
        private ConditionEvaluator $conditions,
        private ChecklistDealGateway $deals,
        private DocumentTemplateReader $templates,
        private DealConditionContextFactory $contexts,
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
    ) {}

    public function generate(int $dealId, int $actorId): ChecklistResult
    {
        return $this->transactions->run(ActorSource::User, $actorId, function () use ($dealId, $actorId): ChecklistResult {
            $deal = $this->deals->lock($dealId);
            $context = $this->contexts->make($deal);
            $templates = $this->templates->activeForVersion($deal->programVersionId);
            $created = [];

            foreach ($templates as $template) {
                if ($template->condition !== null && ! $this->conditions->evaluate($template->condition, $context)->passed) {
                    continue;
                }

                $document = DealDocument::query()->firstOrCreate(
                    [
                        'deal_id' => $deal->id,
                        'source_doc_template_id' => $template->id,
                    ],
                    $this->snapshot($deal, $template),
                );

                if ($document->wasRecentlyCreated) {
                    $created[] = $document->id;
                }
            }

            if ($created !== []) {
                $this->activities->record(
                    action: 'deal.checklist_generated',
                    payload: ['document_ids' => $created, 'document_count' => count($created)],
                    actorId: $actorId,
                    dealId: $deal->id,
                );
                event(new ChecklistGenerated($deal->id, $created, (string) $actorId));
            }

            return new ChecklistResult($created);
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(ChecklistDeal $deal, DocumentTemplateData $template): array
    {
        return [
            'source_program_version_id' => $deal->programVersionId,
            'name_snapshot' => $template->name,
            'description_snapshot' => $template->description,
            'required_snapshot' => $template->required || $template->condition !== null,
            'condition_snapshot' => $template->condition,
            'condition_matches' => $template->condition === null ? null : true,
            'status' => 'to_request',
        ];
    }
}
