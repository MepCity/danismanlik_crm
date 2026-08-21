<x-filament-panels::page>
    @if ($filterLabel !== null)<div class="operations-filter-banner" role="status">{{ __('reporting.dashboard.active_filter', ['filter' => $filterLabel]) }}</div>@endif
    @php($boardDeals = $dealsByStatus->flatten())
    <div class="pipeline-workspace" x-data="{ draggedDeal: null }" data-testid="deal-board">
        <header class="pipeline-toolbar">
            <div class="pipeline-toolbar__intro"><span class="operations-label">{{ __('operations.board.eyebrow') }}</span><p>{{ __('operations.board.description') }}</p></div>
            <dl class="pipeline-overview">
                <div><dt>{{ __('operations.board.metrics.active') }}</dt><dd class="numeric-data">{{ $boardDeals->count() }}</dd></div>
                <div><dt>{{ __('operations.board.metrics.missing') }}</dt><dd class="numeric-data">{{ $boardDeals->sum('missing_documents_count') }}</dd></div>
                <div><dt>{{ __('operations.board.metrics.unassigned') }}</dt><dd class="numeric-data">{{ $boardDeals->whereNull('pm_user_id')->count() }}</dd></div>
            </dl>
            <div class="pipeline-toolbar__hint"><span aria-hidden="true">↔</span>{{ __('operations.board.drag_hint') }}</div>
        </header>
        <div class="pipeline-board-shell">
        <div class="pipeline-board" aria-label="{{ __('operations.board.title') }}" tabindex="0">
            @foreach ($statuses as $status)
                @php($deals = $dealsByStatus->get($status->id, collect()))
                <section class="pipeline-stage" aria-labelledby="status-{{ $status->id }}"
                    @dragover.prevent @dragenter.prevent="$el.classList.add('pipeline-stage--target')"
                    @dragleave="$el.classList.remove('pipeline-stage--target')"
                    @drop.prevent="$el.classList.remove('pipeline-stage--target'); if (draggedDeal) { $wire.moveDeal(draggedDeal, {{ $status->id }}); draggedDeal = null }">
                    <header class="pipeline-stage__header"><span id="status-{{ $status->id }}">{!! \App\Filament\Support\StatusBadge::make($status->color, $status->label) !!}</span><span class="pipeline-stage__count numeric-data">{{ $deals->count() }}</span></header>
                    <div class="pipeline-stage__cards">
                        @forelse ($deals as $deal)
                            @php($days = $deal->status_changed_at->startOfDay()->diffInDays(now()->startOfDay()))
                            <article class="pipeline-card {{ $days >= $delayedStatusDays ? 'pipeline-card--delayed' : '' }}" draggable="true" tabindex="0" role="button"
                                @dragstart="draggedDeal = {{ $deal->id }}; $el.classList.add('pipeline-card--dragging')"
                                @dragend="draggedDeal = null; $el.classList.remove('pipeline-card--dragging')"
                                @click="$wire.openDeal({{ $deal->id }})" @keydown.enter.prevent="$wire.openDeal({{ $deal->id }})">
                                <div class="pipeline-card__topline"><span class="pipeline-card__company">{{ $deal->company->legal_name }}</span><span class="pipeline-card__grip" aria-hidden="true">⠿</span></div>
                                <div class="pipeline-card__context"><span class="pipeline-card__reference numeric-data">{{ $deal->reference_no }}</span><span class="pipeline-card__program">{{ $deal->programVersion->program->name }}</span></div>
                                <div class="pipeline-card__footer">
                                    <span class="pipeline-card__owner"><span aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($deal->projectManager?->name ?? '?') }}</span>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</span>
                                    <span class="numeric-data">{{ $days === 0 ? __('operations.board.today') : __('operations.board.days', ['count' => $days]) }}</span>
                                </div>
                                <div class="pipeline-card__progress"><span style="width: {{ $deal->documents_count > 0 ? round(($deal->received_documents_count / $deal->documents_count) * 100) : 0 }}%"></span></div>
                                <div class="pipeline-card__documents numeric-data">{{ __('operations.board.counter', ['received' => $deal->received_documents_count, 'total' => $deal->documents_count, 'missing' => $deal->missing_documents_count, 'review' => $deal->review_documents_count, 'expired' => $deal->expired_documents_count]) }}</div>
                                @if ($deal->pending_suggestions_count > 0)
                                    <div class="pipeline-card__suggestion">{{ __('operations.board.pending_suggestion', ['count' => $deal->pending_suggestions_count]) }}</div>
                                @endif
                            </article>
                        @empty
                            <div class="pipeline-stage__empty">{{ __('operations.board.empty_column') }}</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
        <div class="pipeline-scroll-cue" aria-hidden="true"><span>↔</span>{{ __('operations.board.scroll_hint') }}</div>
        </div>
    </div>

    @if ($selectedDeal)
        <div class="record-peek-backdrop" wire:click="closeDeal"></div>
        <aside class="record-peek" aria-label="{{ __('operations.board.detail_title') }}" data-testid="deal-detail-drawer">
            <header class="record-peek__header"><div><span class="operations-label numeric-data">{{ $selectedDeal->reference_no }}</span><h2>{{ $selectedDeal->company->legal_name }}</h2></div><button type="button" class="record-peek__close" wire:click="closeDeal" aria-label="{{ __('marketing.transition.cancel') }}">×</button></header>
            <div class="record-peek__status">{!! \App\Filament\Support\StatusBadge::make($selectedDeal->status->color, $selectedDeal->status->label) !!}</div>
            <dl class="record-peek__facts"><div><dt>{{ __('marketing.detail.program') }}</dt><dd>{{ $selectedDeal->programVersion->program->name }}</dd></div><div><dt>{{ __('operations.assignment.manager') }}</dt><dd>{{ $selectedDeal->projectManager?->name ?? __('operations.board.unassigned') }}</dd></div><div><dt>{{ __('marketing.contacts.person') }}</dt><dd>{{ $selectedDeal->company->contacts->first()?->full_name ?? '—' }}</dd></div><div><dt>{{ __('panel.fields.city') }}</dt><dd>{{ $selectedDeal->company->city }}</dd></div></dl>
            <section class="record-peek__activity"><span class="operations-label">{{ __('marketing.board.last_interaction') }}</span><p>{{ $selectedDeal->interactions->first()?->note ?? __('marketing.board.none') }}</p></section>
            <a class="operations-button operations-button--primary" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $selectedDeal->id]) }}">{{ __('marketing.board.open_full_detail') }}</a>
        </aside>
    @endif

    @if($transitionDealId)
        <section class="operations-panel transition-drawer" data-testid="deal-assignment-drawer">
            <header><div><span class="operations-label">{{ __('operations.assignment.manager') }}</span><h3>{{ __('operations.board.complete_move') }}</h3></div><button type="button" class="operations-link" wire:click="cancelMove">{{ __('marketing.transition.cancel') }}</button></header>
            <form wire:submit="assignAndMove" class="operations-inline-form"><label>{{ __('operations.assignment.manager') }}<select wire:model="projectManagerId" required><option value="">{{ __('operations.assignment.choose_manager') }}</option>@foreach($projectManagers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select></label><button class="operations-button operations-button--primary" type="submit">{{ __('operations.assignment.assign') }}</button></form>
        </section>
    @endif
</x-filament-panels::page>
