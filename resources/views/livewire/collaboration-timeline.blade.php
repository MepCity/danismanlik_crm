@php($view = \App\Filament\Support\CollaborationView::class)
@php($isCustomer = $variant === 'customer')

<section
    class="timeline-panel {{ $embedded ? 'timeline-panel--embedded' : 'operations-panel' }} {{ $isCustomer ? 'timeline-panel--customer' : '' }}"
    data-testid="timeline"
    data-variant="{{ $variant }}"
>
    @unless ($embedded)
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
    @endunless

    @if ($isCustomer)
        {{-- Customer workspace: day grouped feed with note surfaces. --}}
        <div class="timeline-days">
            @forelse ($view::dayGroups($timeline) as $group)
                <section class="timeline-day" data-testid="timeline-day">
                    <h3 class="timeline-day__label" data-testid="timeline-day-label">{{ $group['label'] }}</h3>

                    <ol class="timeline-day__items">
                        @foreach ($group['items'] as $item)
                            @php($isNote = $view::isNote($item))
                            <li
                                class="timeline-entry timeline-entry--{{ $view::eventTone($item->type) }}"
                                data-type="{{ $item->type }}"
                                data-testid="timeline-entry"
                            >
                                <span class="timeline-entry__icon" aria-hidden="true">
                                    <x-filament::icon :icon="$view::eventIcon($item->type)" />
                                </span>

                                <div class="timeline-entry__body">
                                    <header class="timeline-entry__meta">
                                        <span class="timeline-entry__avatar" aria-hidden="true">{{ $view::initials($item->actor) }}</span>
                                        <strong class="timeline-entry__actor">{{ $item->actor }}</strong>
                                        @if ($isNote)
                                            <span class="timeline-entry__lead">{{ __('collaboration.activity.note_added') }}</span>
                                        @endif
                                        <time class="timeline-entry__time numeric-data" datetime="{{ $item->occurredAt->toIso8601String() }}">
                                            {{ $item->occurredAt->format('d.m.Y H:i') }}
                                        </time>
                                    </header>

                                    @if ($isNote)
                                        <p class="timeline-entry__note" data-testid="timeline-note">
                                            {{ $view::commentText($view::noteBody($item)) }}
                                        </p>
                                    @else
                                        <p class="timeline-entry__sentence">{{ $view::commentText($item->sentence) }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @empty
                <p class="collaboration-empty">{{ __('collaboration.timeline.empty') }}</p>
            @endforelse
        </div>
    @else
        {{-- Shared default feed for deal, lead, program and document screens. --}}
        <div class="timeline-list">
            @forelse ($timeline as $item)
                <article class="timeline-item {{ $item->actor === __('collaboration.activity.system') ? 'timeline-item--system' : '' }}" data-type="{{ $item->type }}">
                    <time class="timeline-item__time numeric-data" datetime="{{ $item->occurredAt->toIso8601String() }}">{{ $item->occurredAt->format('d.m.Y H:i') }}</time>
                    <strong class="timeline-item__actor">{{ $item->actor }}</strong>
                    <p class="timeline-item__sentence">{{ $view::commentText($item->sentence) }}</p>
                </article>
            @empty
                <p class="collaboration-empty">{{ __('collaboration.timeline.empty') }}</p>
            @endforelse
        </div>
    @endif

    @if ($timeline->hasPages())
        <footer class="timeline-pagination">
            <button type="button" wire:click="previousPage" @disabled($timeline->onFirstPage()) class="operations-button">{{ __('collaboration.timeline.previous') }}</button>
            <span class="numeric-data">{{ __('collaboration.timeline.page', ['current' => $timeline->currentPage(), 'last' => $timeline->lastPage()]) }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $timeline->hasMorePages()) class="operations-button">{{ __('collaboration.timeline.next') }}</button>
        </footer>
    @endif
</section>
