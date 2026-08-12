<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\WithdrawCallConsent;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Support\Authorization\ScopedQuery;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class LeadDetail extends Page
{
    protected static ?string $slug = 'firsatlar/{lead}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.lead-detail';

    public int $leadId;

    public string $interactionType = 'call';

    public string $interactionOccurredAt = '';

    public ?int $interactionDuration = null;

    public string $interactionOutcome = '';

    public string $interactionNote = '';

    public string $activeTab = 'general';

    public function mount(int $lead): void
    {
        $this->leadId = $lead;
        $this->interactionOccurredAt = now()->format('Y-m-d\TH:i');
        $this->lead();
    }

    public function hydrate(): void
    {
        $this->lead();
    }

    public function getTitle(): string
    {
        return __('marketing.detail.title', ['company' => $this->lead()->company->legal_name]);
    }

    public function addInteraction(RecordInteraction $interactions): void
    {
        $this->validate([
            'interactionType' => ['required', 'in:call,incoming_call,meeting,email'],
            'interactionOccurredAt' => ['required', 'date'],
            'interactionDuration' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'interactionOutcome' => ['nullable', 'string', 'max:255'],
            'interactionNote' => ['nullable', 'string', 'max:5000'],
        ]);
        Gate::authorize('create', Interaction::class);
        if ($this->interactionType === 'incoming_call') {
            $interactions->forInboundLeadCall($this->leadId, (int) Auth::id(), Carbon::parse($this->interactionOccurredAt), $this->interactionDuration, $this->interactionOutcome ?: null, $this->interactionNote ?: null);
        } else {
            $interactions->forLead($this->leadId, (int) Auth::id(), $this->interactionType, Carbon::parse($this->interactionOccurredAt), $this->interactionDuration, $this->interactionOutcome ?: null, $this->interactionNote ?: null);
        }
        $this->interactionOccurredAt = now()->format('Y-m-d\TH:i');
        $this->reset('interactionDuration', 'interactionOutcome', 'interactionNote');
        Notification::make()->title(__('marketing.messages.interaction_saved'))->success()->send();
    }

    public function doNotCall(int $contactId, WithdrawCallConsent $consents): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        abort_unless($contact->company_id === $this->lead()->company_id, 404);
        Gate::authorize('update', $contact);
        $consents->handle($contact->id, (int) Auth::id());
        Notification::make()->title(__('marketing.messages.do_not_call_saved'))->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['lead' => $this->lead()->load([
            'company.contacts.communicationConsents' => fn ($query) => $query->where('channel', 'call')->where('purpose', 'marketing')->latest('effective_from'),
            'owner', 'status', 'interestedProgramVersion.program',
            'interactions' => fn ($query) => $query->with('user')->latest('occurred_at'),
            'convertedDeal',
        ])];
    }

    private function lead(): Lead
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $lead = Lead::query()->with('company')->findOrFail($this->leadId);
        abort_unless(app(ScopedQuery::class)->contains($user, $lead, 'view'), 403);

        return $lead;
    }
}
