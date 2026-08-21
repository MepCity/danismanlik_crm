<x-filament-panels::page>
    @if ($filterLabel !== null)<div class="operations-filter-banner" role="status">{{ __('reporting.dashboard.active_filter', ['filter' => $filterLabel]) }}</div>@endif
    @php($overdueCount = $leads->filter(fn ($lead) => $lead->next_call_at->isBefore(today()))->count())
    <div class="call-workspace" data-testid="today-calls">
        <header class="call-workspace__header">
            <div>
                <span class="operations-label">{{ __('marketing.calls.workspace_eyebrow') }}</span>
                <h2>{{ __('marketing.calls.workspace_title') }}</h2>
                <p>{{ __('marketing.calls.workspace_description') }}</p>
            </div>
            <div class="call-workspace__metrics">
                <div><strong class="numeric-data">{{ $leads->count() }}</strong><span>{{ __('marketing.calls.due_count') }}</span></div>
                <div class="{{ $overdueCount > 0 ? 'is-urgent' : '' }}"><strong class="numeric-data">{{ $overdueCount }}</strong><span>{{ __('marketing.calls.overdue_count') }}</span></div>
            </div>
        </header>

        <nav class="call-filter-tabs" aria-label="{{ __('marketing.calls.filter_label') }}">
            <a href="{{ \App\Filament\Pages\TodayCalls::getUrl(['filter' => 'due']) }}" class="{{ $filter === 'due' ? 'is-active' : '' }}">{{ __('marketing.calls.all_due') }}</a>
            <a href="{{ \App\Filament\Pages\TodayCalls::getUrl(['filter' => 'today']) }}" class="{{ $filter === 'today' ? 'is-active' : '' }}">{{ __('marketing.calls.today') }}</a>
            <a href="{{ \App\Filament\Pages\TodayCalls::getUrl(['filter' => 'overdue']) }}" class="{{ $filter === 'overdue' ? 'is-active' : '' }}">{{ __('marketing.calls.overdue') }}</a>
        </nav>

        <section class="call-ledger" aria-label="{{ __('marketing.calls.title') }}">
            @forelse ($leads as $lead)
                @php($contact = $lead->company->contacts->first())
                @php($isOverdue = $lead->next_call_at->isBefore(today()))
                <article class="call-ledger__row {{ $isOverdue ? 'is-overdue' : '' }}" data-testid="call-card-{{ $lead->id }}">
                    <div class="call-ledger__time numeric-data"><strong>{{ $lead->next_call_at->format('H:i') }}</strong><span>{{ $isOverdue ? $lead->next_call_at->format('d.m') : __('marketing.calls.today') }}</span></div>
                    <div class="call-ledger__identity">
                        <a href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}">{{ $lead->company->legal_name }}</a>
                        <span>{{ $contact?->full_name ?? __('marketing.calls.no_contact') }}@if($contact?->title) · {{ $contact->title }}@endif</span>
                    </div>
                    <div class="call-ledger__context"><strong>{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</strong><span>{{ __('marketing.calls.call_number', ['count' => $lead->interactions_count + 1]) }} · {{ __('marketing.calls.last_outcome') }}: {{ $lead->interactions->first()?->outcome ? __('marketing.interactions.outcomes.'.$lead->interactions->first()->outcome) : __('marketing.calls.no_interaction') }}</span></div>
                    <div class="call-ledger__actions">
                        @if($contact?->phone)<a class="call-ledger__phone numeric-data" href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a>@endif
                        <button type="button" class="operations-button" wire:click="chooseOutcome({{ $lead->id }}, 'contacted')">{{ __('marketing.calls.log_result') }}</button>
                    </div>

                    @if($activeLeadId === $lead->id)
                        <form wire:submit="saveOutcome" class="call-composer">
                            <div class="call-composer__outcomes" aria-label="{{ __('marketing.interactions.quick_result') }}">@foreach($outcomes as $value => $label)<button type="button" class="{{ $quickOutcome === $value ? 'is-selected' : '' }}" wire:click="chooseOutcome({{ $lead->id }}, '{{ $value }}')">{{ $label }}</button>@endforeach</div>
                            <label><span>{{ __('marketing.interactions.note_optional') }}</span><textarea wire:model="quickNote" rows="2"></textarea></label>
                            <div class="call-composer__footer"><button type="button" class="operations-link" wire:click="$set('activeLeadId', null)">{{ __('marketing.transition.cancel') }}</button><button class="operations-button operations-button--primary" type="submit">{{ __('marketing.interactions.save_result') }}</button></div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="call-ledger__empty">{{ __('marketing.calls.empty') }}</div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
