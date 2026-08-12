<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Services\EmailNotificationService;
use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Document\Services\AdHocDocumentService;
use App\Domain\Document\Services\DocumentAccessService;
use App\Domain\Document\Services\DocumentRequestService;
use App\Domain\Document\Services\DocumentRequirementDecisionService;
use App\Domain\Document\Services\DocumentStatusService;
use App\Domain\Document\Services\DocumentUploadService;
use App\Filament\Support\DealOperationsView;
use App\Support\Authorization\ScopedQuery;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class DealDetail extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $slug = 'dosyalar/{deal}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.deal-detail';

    public int $dealId;

    public string $activeTab = 'general';

    public ?int $uploadDocumentId = null;

    public ?TemporaryUploadedFile $upload = null;

    public ?int $decisionDocumentId = null;

    public string $decisionTarget = '';

    public string $decisionReason = '';

    public string $adHocName = '';

    public string $adHocDescription = '';

    public bool $adHocRequired = true;

    public ?string $transitionError = null;

    public string $interactionType = 'call';

    public string $interactionOccurredAt = '';

    public ?int $interactionDuration = null;

    public string $interactionOutcome = '';

    public string $interactionNote = '';

    public function mount(int $deal): void
    {
        $this->dealId = $deal;
        $this->interactionOccurredAt = now()->format('Y-m-d\TH:i');
        $this->authorizeDeal();
    }

    public function hydrate(): void
    {
        $this->authorizeDeal();
    }

    public function getTitle(): string
    {
        return __('operations.detail.title', ['reference' => $this->deal()->reference_no]);
    }

    public function transitionDeal(int $targetStatusId, StatusMachineContract $machine): void
    {
        $deal = $this->deal();
        Gate::authorize('update', $deal);
        $this->transitionError = null;

        try {
            $machine->transition(new StatusTransition(
                SubjectType::Deal,
                $deal->id,
                $targetStatusId,
                (int) Auth::id(),
            ));
            $this->success(__('operations.messages.transitioned'));
        } catch (StatusTransitionRejected $exception) {
            $this->transitionError = $exception->getMessage();
        }
    }

    public function addInteraction(RecordInteraction $interactions): void
    {
        $this->validate([
            'interactionType' => ['required', 'in:call,meeting,email'],
            'interactionOccurredAt' => ['required', 'date'],
            'interactionDuration' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'interactionOutcome' => ['nullable', 'string', 'max:255'],
            'interactionNote' => ['nullable', 'string', 'max:5000'],
        ]);
        $deal = $this->deal();
        Gate::authorize('create', Interaction::class);
        $interactions->forDeal($deal->id, (int) Auth::id(), $this->interactionType, Carbon::parse($this->interactionOccurredAt), $this->interactionDuration, $this->interactionOutcome ?: null, $this->interactionNote ?: null);
        $this->interactionOccurredAt = now()->format('Y-m-d\TH:i');
        $this->reset('interactionDuration', 'interactionOutcome', 'interactionNote');
        $this->success(__('marketing.messages.interaction_saved'));
    }

    public function uploadDocument(DocumentUploadService $documents): void
    {
        $this->validate([
            'uploadDocumentId' => ['required', 'integer'],
            'upload' => ['required', 'file', 'max:'.(int) config('documents.max_size_kb')],
        ]);
        $document = $this->document((int) $this->uploadDocumentId);
        Gate::authorize('update', $document);
        $upload = $this->upload;
        abort_unless($upload instanceof TemporaryUploadedFile, 422);
        $result = $documents->upload($document->id, $upload, (int) Auth::id());
        $this->reset('uploadDocumentId', 'upload');
        $this->success(__('operations.messages.uploaded', ['version' => $result->file->version_no]));
    }

    public function startReview(int $documentId, DocumentStatusService $documents): void
    {
        $this->authorizeApproval($this->document($documentId));
        $documents->startReview($documentId, (int) Auth::id());
        $this->success(__('operations.messages.review_started'));
    }

    public function decide(DocumentStatusService $documents): void
    {
        $rules = [
            'decisionDocumentId' => ['required', 'integer'],
            'decisionTarget' => ['required', 'in:accepted,rejected,new_version_expected'],
            'decisionReason' => in_array($this->decisionTarget, ['rejected', 'new_version_expected'], true)
                ? ['required', 'string', 'min:3'] : ['nullable', 'string'],
        ];
        $this->validate($rules);
        $document = $this->document((int) $this->decisionDocumentId);
        $this->authorizeApproval($document);
        $documents->decide($document->id, $this->decisionTarget, $this->decisionReason ?: null, (int) Auth::id());
        $this->reset('decisionDocumentId', 'decisionTarget', 'decisionReason');
        $this->success(__('operations.messages.decision_saved'));
    }

    public function decideSuggestion(int $suggestionId, bool $accept, DocumentRequirementDecisionService $service): void
    {
        $suggestion = DocumentRequirementSuggestion::query()->with('dealDocument')->findOrFail($suggestionId);
        abort_unless($suggestion->dealDocument->deal_id === $this->dealId, 404);
        $this->authorizeApproval($suggestion->dealDocument);
        $service->decide($suggestionId, (int) Auth::id(), $accept);
        $this->success(__('operations.messages.suggestion_decided'));
    }

    public function addAdHoc(AdHocDocumentService $service): void
    {
        $this->validate([
            'adHocName' => ['required', 'string', 'max:255'],
            'adHocDescription' => ['nullable', 'string', 'max:2000'],
            'adHocRequired' => ['boolean'],
        ]);
        $deal = $this->deal();
        Gate::authorize('update', $deal);
        abort_unless(Auth::user()?->can('document.upload') === true, 403);
        $service->create($deal->id, (int) Auth::id(), $this->adHocName, $this->adHocDescription ?: null, $this->adHocRequired);
        $this->reset('adHocName', 'adHocDescription');
        $this->adHocRequired = true;
        $this->success(__('operations.messages.ad_hoc_added'));
    }

    public function download(int $fileId, DocumentAccessService $service): void
    {
        $url = $service->temporaryUrl($fileId, (int) Auth::id());
        $this->redirect($url, navigate: false);
    }

    public function sendMissingDocuments(
        DocumentRequestService $requests,
        EmailNotificationService $emails,
    ): void {
        $deal = $this->deal()->load('company.contacts');
        Gate::authorize('update', $deal);
        abort_unless(Auth::user()?->can('document.upload') === true, 403);
        $contact = $deal->company->contacts
            ->first(fn ($contact): bool => $contact->is_active && $contact->is_primary && $contact->consent_email === true && filled($contact->email));

        if ($contact === null) {
            $this->error(__('operations.messages.no_email_contact'));

            return;
        }

        $missing = DealOperationsView::missingDocuments($deal);

        if ($missing->isEmpty()) {
            $this->error(__('operations.messages.no_missing_documents'));

            return;
        }

        $toRequest = $missing->where('status', 'to_request')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        if ($toRequest !== []) {
            $requests->markRequested($toRequest, (int) Auth::id());
        }

        $body = __('operations.email.greeting', ['name' => $contact->full_name])."\n\n";
        $body .= __('operations.email.intro', ['reference' => $deal->reference_no])."\n";
        $body .= $missing->map(fn (DealDocument $document): string => '• '.$document->name_snapshot)->implode("\n");
        $body .= "\n\n".__('operations.email.closing');
        $emails->queueExternal(
            (string) $contact->email,
            $contact->full_name,
            'deal.missing_documents_requested',
            __('operations.email.subject', ['reference' => $deal->reference_no]),
            $body,
            new SubjectReference(CollaborationSubjectType::Deal, $deal->id),
        );
        $this->success(__('operations.messages.email_queued', ['count' => $missing->count()]));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $deal = $this->deal()->load([
            'company.contacts', 'programVersion.program', 'projectManager', 'openedBy', 'status',
            'interactions' => fn ($query) => $query->with('user')->latest('occurred_at'),
            'documents.files' => fn ($query) => $query->where('is_deleted', false)->orderByDesc('version_no'),
            'documents.requirementSuggestions' => fn ($query) => $query->where('status', 'pending'),
        ]);
        $transitions = Transition::query()->with('toStatus')
            ->where('from_status_id', $deal->status_id)->where('is_active', true)->get()
            ->filter(fn (Transition $transition): bool => $transition->required_permission === null || Auth::user()?->can($transition->required_permission) === true);

        return compact('deal', 'transitions');
    }

    private function deal(): Deal
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $deal = Deal::query()->findOrFail($this->dealId);
        abort_unless(app(ScopedQuery::class)->contains($user, $deal, 'view'), 403);

        return $deal;
    }

    private function document(int $documentId): DealDocument
    {
        $document = DealDocument::query()->findOrFail($documentId);
        abort_unless($document->deal_id === $this->dealId, 404);

        return $document;
    }

    private function authorizeDeal(): void
    {
        $this->deal();
    }

    private function authorizeApproval(DealDocument $document): void
    {
        Gate::authorize('view', $document);
        abort_unless(Auth::user()?->can('document.approve') === true, 403);
    }

    private function success(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }

    private function error(string $title): void
    {
        Notification::make()->title($title)->danger()->send();
    }
}
