<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\WithdrawCallConsent;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Filament\Support\MarketingOperationsView;
use App\Support\Authorization\ScopedQuery;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class TodayCalls extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    protected static ?string $slug = 'bugun-aranacaklar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.today-calls';

    public ?int $activeLeadId = null;

    public string $quickOutcome = '';

    public string $quickNote = '';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Lead::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.calls.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.marketing');
    }

    public function getTitle(): string
    {
        return __('marketing.calls.title');
    }

    public function chooseOutcome(int $leadId, string $outcome): void
    {
        $this->lead($leadId);
        abort_unless(array_key_exists($outcome, __('marketing.interactions.outcomes')), 422);
        $this->activeLeadId = $leadId;
        $this->quickOutcome = $outcome;
        $this->quickNote = '';
    }

    public function saveOutcome(RecordInteraction $interactions): void
    {
        $this->validate([
            'activeLeadId' => ['required', 'integer'],
            'quickOutcome' => ['required', 'string', 'in:unreachable,contacted,interested,not_interested'],
            'quickNote' => ['nullable', 'string', 'max:2000'],
        ]);
        $lead = $this->lead((int) $this->activeLeadId);
        Gate::authorize('create', Interaction::class);
        $interactions->forLead($lead->id, (int) Auth::id(), 'call', Carbon::now(), null, $this->quickOutcome, $this->quickNote ?: null);
        $this->reset('activeLeadId', 'quickOutcome', 'quickNote');
        Notification::make()->title(__('marketing.messages.interaction_saved'))->success()->send();
    }

    public function doNotCall(int $contactId, WithdrawCallConsent $consents): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        Gate::authorize('update', $contact);
        $consents->handle($contact->id, (int) Auth::id());
        Notification::make()->title(__('marketing.messages.do_not_call_saved'))->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        return [
            'leads' => MarketingOperationsView::callsDue(app(ScopedQuery::class)->apply(Lead::query(), $user, 'viewAny')),
            'outcomes' => __('marketing.interactions.outcomes'),
        ];
    }

    private function lead(int $leadId): Lead
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $lead = Lead::query()->findOrFail($leadId);
        abort_unless(app(ScopedQuery::class)->contains($user, $lead, 'update'), 403);

        return $lead;
    }
}
