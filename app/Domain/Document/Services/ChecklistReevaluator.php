<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\NotificationWriter;
use App\Domain\Deal\DTO\ChecklistDeal;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\DTOs\ReevaluationResult;
use App\Domain\Document\Events\ChecklistReevaluated;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Program\Services\DocumentTemplateReader;
use App\Support\Audit\ActorSource;
use App\Support\Conditions\ConditionEvaluator;
use Illuminate\Support\Carbon;

final readonly class ChecklistReevaluator implements ChecklistReevaluatorContract
{
    public function __construct(
        private ConditionEvaluator $conditions,
        private ChecklistDealGateway $deals,
        private DocumentTemplateReader $templates,
        private NotificationWriter $notifications,
        private DealConditionContextFactory $contexts,
        private DocumentTransaction $transactions,
        private ActivityRecorder $activities,
    ) {}

    public function reevaluate(int $dealId): ReevaluationResult
    {
        return $this->transactions->run(ActorSource::Automation, null, function () use ($dealId): ReevaluationResult {
            $deal = $this->deals->lock($dealId);
            $context = $this->contexts->make($deal);
            $templates = $this->templates->activeForVersion($deal->programVersionId, true);
            $documents = DealDocument::query()
                ->where('deal_id', $deal->id)
                ->whereNotNull('source_doc_template_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('source_doc_template_id');
            $createdDocuments = [];
            $createdSuggestions = [];
            $createdNames = [];

            foreach ($templates as $template) {
                $matches = $this->conditions->evaluate($template->condition ?? [], $context)->passed;
                /** @var DealDocument|null $document */
                $document = $documents->get($template->id);

                if ($matches && $document === null) {
                    $document = DealDocument::query()->create([
                        'deal_id' => $deal->id,
                        'source_doc_template_id' => $template->id,
                        'source_program_version_id' => $deal->programVersionId,
                        'name_snapshot' => $template->name,
                        'description_snapshot' => $template->description,
                        'required_snapshot' => true,
                        'condition_snapshot' => $template->condition,
                        'condition_matches' => true,
                        'status' => 'to_request',
                    ]);
                    $createdDocuments[] = $document->id;
                    $createdNames[] = $document->name_snapshot;

                    continue;
                }

                if ($document === null) {
                    continue;
                }

                if ($matches) {
                    $this->markConditionRestored($document);

                    continue;
                }

                if ($document->condition_matches === false || $document->status === 'not_required') {
                    continue;
                }

                $document->update(['condition_matches' => false]);
                $suggestion = DocumentRequirementSuggestion::query()->create([
                    'deal_document_id' => $document->id,
                    'reason' => 'condition_no_longer_matches',
                    'reason_parameters' => ['document' => $document->name_snapshot],
                ]);
                $createdSuggestions[] = $suggestion->id;

                $this->activities->record(
                    action: 'document.requirement_suggested',
                    payload: [
                        'suggestion_id' => $suggestion->id,
                        'document' => ['id' => $document->id, 'name' => $document->name_snapshot],
                        'reason' => $suggestion->reason,
                    ],
                    dealDocumentId: $document->id,
                );
            }

            $this->notifyProjectManager($deal, $createdDocuments, $createdNames);

            if ($createdDocuments !== [] || $createdSuggestions !== []) {
                event(new ChecklistReevaluated($deal->id, $createdDocuments, $createdSuggestions));
            }

            return new ReevaluationResult($createdDocuments, $createdSuggestions);
        });
    }

    private function markConditionRestored(DealDocument $document): void
    {
        if ($document->condition_matches === true) {
            return;
        }

        $document->update(['condition_matches' => true]);
        DocumentRequirementSuggestion::query()
            ->where('deal_document_id', $document->id)
            ->where('status', 'pending')
            ->update(['status' => 'superseded', 'decided_at' => Carbon::now()]);
    }

    /**
     * @param  list<int>  $documentIds
     * @param  list<string>  $names
     */
    private function notifyProjectManager(ChecklistDeal $deal, array $documentIds, array $names): void
    {
        if ($documentIds === [] || $deal->projectManagerId === null) {
            return;
        }

        $this->notifications->conditionDocumentsAdded(
            $deal->projectManagerId,
            $deal->id,
            count($documentIds),
            implode(', ', $names),
        );

        $this->activities->record(
            action: 'deal.condition_documents_added',
            payload: ['document_ids' => $documentIds, 'document_names' => $names],
            dealId: $deal->id,
        );
    }
}
