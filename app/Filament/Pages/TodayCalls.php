<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\Actions\RecordInteraction;
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
use Livewire\Attributes\Url;

final class TodayCalls extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    protected static ?string $slug = 'bugun-aranacaklar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.today-calls';

    #[Url(as: 'filter')]
    public string $filter = 'due';

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
        $interactions->forLead($lead->id, (int) Auth::id(), 'call', Carbon::now(), $this->quickOutcome, $this->quickNote ?: null, $lead->primary_contact_id);
        $this->reset('activeLeadId', 'quickOutcome', 'quickNote');
        Notification::make()->title(__('marketing.messages.interaction_saved'))->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless(in_array($this->filter, ['due', 'today', 'overdue'], true), 404);

        return [
            'leads' => MarketingOperationsView::callsDue(app(ScopedQuery::class)->apply(Lead::query(), $user, 'viewAny'), $this->filter),
            'outcomes' => __('marketing.interactions.outcomes'),
            'filterLabel' => $this->filter === 'due' ? null : __("marketing.calls.filters.{$this->filter}"),
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
