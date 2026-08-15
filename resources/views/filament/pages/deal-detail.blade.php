<x-filament-panels::page>
    <div class="deal-detail" data-testid="deal-detail">
        @php($documentTotal = $deal->documents->count())
        @php($documentCompleted = $deal->documents->whereIn('status', ['accepted', 'not_required'])->count())
        @php($documentMissing = $deal->documents->where('required_snapshot', true)->whereIn('status', ['to_request', 'requested', 'rejected', 'new_version_expected'])->count())
        <section class="deal-detail__summary" aria-label="{{ __('operations.detail.summary.title') }}">
            <div class="deal-detail__summary-item deal-detail__summary-item--status"><span>{{ __('operations.detail.summary.status') }}</span><strong>{!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}</strong></div>
            <div class="deal-detail__summary-item"><span>{{ __('operations.detail.summary.manager') }}</span><strong>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</strong></div>
            <div class="deal-detail__summary-item"><span>{{ __('operations.detail.summary.documents') }}</span><strong class="numeric-data">{{ __('operations.detail.summary.document_progress', ['completed' => $documentCompleted, 'total' => $documentTotal]) }}</strong><small class="{{ $documentMissing > 0 ? 'is-risk' : '' }}">{{ __('operations.detail.summary.missing', ['count' => $documentMissing]) }}</small></div>
            <div class="deal-detail__summary-item"><span>{{ __('operations.detail.summary.updated') }}</span><strong class="numeric-data">{{ $deal->updated_at->format('d.m.Y H:i') }}</strong></div>
        </section>

        <nav class="deal-tabs" aria-label="{{ __('operations.detail.title', ['reference' => $deal->reference_no]) }}">
            @foreach (['general', 'process', 'documents', 'tasks', 'comments', 'interactions', 'team', 'history'] as $tab)
                <button type="button" wire:click="$set('activeTab', '{{ $tab }}')" class="deal-tab {{ $activeTab === $tab ? 'deal-tab--active' : '' }}">
                    {{ __('operations.detail.tabs.'.$tab) }}
                </button>
            @endforeach
        </nav>

        <div class="deal-detail__content" wire:key="deal-detail-tab-{{ $activeTab }}">
        @if ($activeTab === 'general')
            @php($wonHistory = $deal->originatingLead?->statusHistory?->first(fn ($history) => $history->status->converts_to_deal))
            @php($saleInteraction = $deal->originatingLead?->interactions?->first())
            <section class="operations-panel operations-facts">
                @foreach ([
                    __('operations.detail.fields.company') => $deal->company->legal_name,
                    __('operations.detail.fields.reference') => $deal->reference_no,
                    __('operations.detail.fields.program') => $deal->programVersion->program->name.' · '.$deal->programVersion->call_period,
                    __('operations.detail.fields.manager') => $deal->projectManager?->name ?? __('operations.board.unassigned'),
                    __('operations.detail.fields.opened_by') => $deal->openedBy->name,
                    __('operations.detail.fields.marketer') => $deal->originatingLead?->owner?->name ?? $deal->openedBy->name,
                    __('operations.detail.fields.contacted_person') => $deal->originatingLead?->primaryContact?->full_name ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.won_at') => $wonHistory?->entered_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.sale_summary') => $saleInteraction?->note ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.priority') => $deal->priority,
                    __('operations.detail.fields.amount') => $deal->requested_amount ? number_format((float) $deal->requested_amount, 2, ',', '.').' ₺' : __('operations.detail.not_recorded'),
                    __('operations.detail.fields.application_no') => $deal->application_no ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.applied_at') => $deal->applied_at?->format('d.m.Y') ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.decided_at') => $deal->decided_at?->format('d.m.Y') ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.document_requested_at') => $deal->document_requested_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.first_document_received_at') => $deal->first_document_received_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
                    __('operations.detail.fields.all_required_accepted_at') => $deal->all_required_accepted_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
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
                            @if ($transition->required_permission === 'deal.assign' && $deal->pm_user_id === null)
                                <label>
                                    {{ __('operations.assignment.manager') }}
                                    <select wire:model="projectManagerId" required>
                                        <option value="">{{ __('operations.assignment.choose_manager') }}</option>
                                        @foreach ($projectManagers as $projectManager)<option value="{{ $projectManager->id }}">{{ $projectManager->name }}</option>@endforeach
                                    </select>
                                </label>
                                <button type="button" wire:click="assignProjectManager({{ $transition->to_status_id }})" class="operations-button operations-button--primary">
                                    {{ __('operations.assignment.assign') }}
                                </button>
                            @else
                                <button type="button" wire:click="transitionDeal({{ $transition->to_status_id }})" class="operations-button operations-button--primary">
                                    {{ $transition->toStatus->label }}
                                </button>
                            @endif
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
        @elseif ($activeTab === 'tasks')
            <livewire:subject-tasks subject-type="deal" :subject-id="$deal->id" :key="'deal-tasks-'.$deal->id" />
        @elseif ($activeTab === 'comments')
            <livewire:collaboration-comments subject-type="deal" :subject-id="$deal->id" :key="'deal-comments-'.$deal->id" />
        @elseif ($activeTab === 'interactions')
            @include('filament.pages.partials.interactions', ['interactions' => $deal->interactions, 'contacts' => $deal->company->contacts->where('is_active', true)])
        @elseif ($activeTab === 'team')
            @php($teamMembers = $deal->projectManager?->teams->flatMap->members->unique('id') ?? collect())
            <section class="team-grid" data-testid="deal-team">
                <article class="operations-panel team-card"><span>{{ __('operations.detail.team.manager') }}</span><strong>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</strong></article>
                <article class="operations-panel team-card"><span>{{ __('operations.detail.team.opened_by') }}</span><strong>{{ $deal->openedBy->name }}</strong></article>
                @foreach ($teamMembers as $member)<article class="operations-panel team-card"><span>{{ __('operations.detail.team.member') }}</span><strong>{{ $member->name }}</strong></article>@endforeach
            </section>
        @elseif ($activeTab === 'history')
            <livewire:collaboration-timeline subject-type="deal" :subject-id="$deal->id" :key="'deal-timeline-'.$deal->id" />
        @endif
        </div>
    </div>
</x-filament-panels::page>
