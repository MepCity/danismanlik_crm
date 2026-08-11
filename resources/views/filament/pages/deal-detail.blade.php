<x-filament-panels::page>
    <div class="deal-detail" data-testid="deal-detail">
        <nav class="deal-tabs" aria-label="{{ __('operations.detail.title', ['reference' => $deal->reference_no]) }}">
            @foreach (['general', 'process', 'documents', 'tasks', 'comments', 'interactions', 'team', 'history'] as $tab)
                <button type="button" wire:click="$set('activeTab', '{{ $tab }}')" class="deal-tab {{ $activeTab === $tab ? 'deal-tab--active' : '' }}">
                    {{ __('operations.detail.tabs.'.$tab) }}
                </button>
            @endforeach
        </nav>

        @if ($activeTab === 'general')
            <section class="operations-panel operations-facts">
                @foreach ([
                    __('operations.detail.fields.company') => $deal->company->legal_name,
                    __('operations.detail.fields.reference') => $deal->reference_no,
                    __('operations.detail.fields.program') => $deal->programVersion->program->name.' · '.$deal->programVersion->call_period,
                    __('operations.detail.fields.manager') => $deal->projectManager?->name ?? __('operations.board.unassigned'),
                    __('operations.detail.fields.opened_by') => $deal->openedBy->name,
                    __('operations.detail.fields.priority') => $deal->priority,
                ] as $label => $value)
                    <div class="operations-fact"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </section>
        @elseif ($activeTab === 'process')
            <section class="operations-panel process-panel">
                <div>
                    <div class="operations-label">{{ __('operations.detail.fields.status') }}</div>
                    {!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}
                    <div class="operations-muted">{{ __('operations.detail.fields.status_since') }}: {{ $deal->status_changed_at->format('d.m.Y H:i') }}</div>
                </div>
                <div>
                    <h3>{{ __('operations.detail.allowed_transitions') }}</h3>
                    <div class="operations-actions">
                        @forelse ($transitions as $transition)
                            <button type="button" wire:click="transitionDeal({{ $transition->to_status_id }})" class="operations-button operations-button--primary">
                                {{ $transition->toStatus->label }}
                            </button>
                        @empty
                            <p class="operations-muted">{{ __('operations.detail.no_transitions') }}</p>
                        @endforelse
                    </div>
                </div>
                @if ($transitionError)
                    <div class="operations-error" role="alert" data-testid="transition-error">
                        <strong>{{ __('operations.detail.transition_error') }}</strong>
                        <span>{{ $transitionError }}</span>
                    </div>
                @endif
            </section>
        @elseif ($activeTab === 'documents')
            @include('filament.pages.partials.document-checklist')
        @elseif ($activeTab === 'interactions')
            @include('filament.pages.partials.interactions', ['interactions' => $deal->interactions])
        @else
            <section class="operations-panel operations-placeholder">
                <span aria-hidden="true">◇</span>
                <p>{{ __('operations.detail.placeholder') }}</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>
