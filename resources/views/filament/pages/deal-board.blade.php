<x-filament-panels::page>
    <div class="deal-board" data-testid="deal-board">
        @foreach ($statuses as $status)
            @php($deals = $dealsByStatus->get($status->id, collect()))
            <section class="deal-column" aria-labelledby="status-{{ $status->id }}">
                <header class="deal-column__header">
                    <span id="status-{{ $status->id }}">{!! \App\Filament\Support\StatusBadge::make($status->color, $status->label) !!}</span>
                    <span class="numeric-data">{{ $deals->count() }}</span>
                </header>
                <div class="deal-column__cards">
                    @forelse ($deals as $deal)
                        @php($days = $deal->status_changed_at->startOfDay()->diffInDays(now()->startOfDay()))
                        <a href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $deal->id]) }}"
                           class="deal-card {{ $days >= $delayedStatusDays ? 'deal-card--delayed' : '' }}">
                            <div class="deal-card__title">{{ $deal->company->legal_name }}</div>
                            <div class="deal-card__reference numeric-data">{{ $deal->reference_no }}</div>
                            <div class="deal-card__meta">
                                <span>{{ $deal->programVersion->program->name }}</span>
                                <span>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</span>
                            </div>
                            <div class="deal-card__age {{ $days >= $delayedStatusDays ? 'deal-card__age--delayed' : '' }}">
                                <span aria-hidden="true">◷</span>
                                {{ $days === 0 ? __('operations.board.today') : __('operations.board.days', ['count' => $days]) }}
                                @if ($days >= $delayedStatusDays)<span class="sr-only">{{ __('operations.board.delayed') }}</span>@endif
                            </div>
                            <div class="deal-card__counters numeric-data">
                                {{ __('operations.board.counter', [
                                    'received' => $deal->received_documents_count, 'total' => $deal->documents_count,
                                    'missing' => $deal->missing_documents_count, 'review' => $deal->review_documents_count,
                                    'expired' => $deal->expired_documents_count,
                                ]) }}
                            </div>
                            @if ($deal->pending_suggestions_count > 0)
                                <div class="deal-warning" data-testid="pending-suggestion">
                                    <span aria-hidden="true">!</span>
                                    {{ __('operations.board.pending_suggestion', ['count' => $deal->pending_suggestions_count]) }}
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="deal-column__empty">{{ __('operations.board.empty_column') }}</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
