<section class="operations-panel timeline-panel" data-testid="timeline">
    <header class="collaboration-header">
        <div><h2>{{ __('collaboration.timeline.title') }}</h2><p>{{ __('collaboration.timeline.description') }}</p></div>
        <div class="timeline-filters" aria-label="{{ __('collaboration.timeline.filters.label') }}">
            @foreach (['all', 'status', 'document', 'comment'] as $option)
                <button type="button" wire:click="setFilter('{{ $option }}')" class="timeline-filter {{ $filter === $option ? 'timeline-filter--active' : '' }}">
                    {{ __('collaboration.timeline.filters.'.$option) }}
                </button>
            @endforeach
        </div>
    </header>

    <div class="timeline-list">
        @forelse ($timeline as $item)
            <article class="timeline-item {{ $item->actor === __('collaboration.activity.system') ? 'timeline-item--system' : '' }}" data-type="{{ $item->type }}">
                <time class="timeline-item__time numeric-data" datetime="{{ $item->occurredAt->toIso8601String() }}">{{ $item->occurredAt->format('d.m.Y H:i') }}</time>
                <strong class="timeline-item__actor">{{ $item->actor }}</strong>
                <p class="timeline-item__sentence">{{ $item->sentence }}</p>
            </article>
        @empty
            <p class="collaboration-empty">{{ __('collaboration.timeline.empty') }}</p>
        @endforelse
    </div>

    @if ($timeline->hasPages())
        <footer class="timeline-pagination">
            <button type="button" wire:click="previousPage" @disabled($timeline->onFirstPage()) class="operations-button">{{ __('collaboration.timeline.previous') }}</button>
            <span class="numeric-data">{{ __('collaboration.timeline.page', ['current' => $timeline->currentPage(), 'last' => $timeline->lastPage()]) }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $timeline->hasMorePages()) class="operations-button">{{ __('collaboration.timeline.next') }}</button>
        </footer>
    @endif
</section>
