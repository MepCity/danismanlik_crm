<x-filament-panels::page>
    @php($documentTotal = $deal->documents->count())
    @php($documentCompleted = $deal->documents->whereIn('status', ['accepted', 'not_required'])->count())
    @php($documentMissing = $deal->documents->where('required_snapshot', true)->whereIn('status', ['to_request', 'requested', 'rejected', 'new_version_expected'])->count())
    @php($documentProgress = $documentTotal > 0 ? round(($documentCompleted / $documentTotal) * 100) : 0)
    @php($wonHistory = $deal->originatingLead?->statusHistory?->first(fn ($history) => $history->status->converts_to_deal))
    @php($saleInteraction = $deal->originatingLead?->interactions?->first())
    @php($teamMembers = $deal->projectManager?->teams->flatMap->members->unique('id') ?? collect())
    @php($serviceWorkflow = $deal->programVersion->workflow_snapshot)

    <div class="deal-workspace" data-testid="deal-detail">
        <header class="deal-hero">
            <div class="deal-hero__identity">
                <div class="deal-hero__eyebrow">
                    <span class="numeric-data">{{ $deal->reference_no }}</span>
                    <span aria-hidden="true">/</span>
                    <span>{{ $deal->programVersion->program->name }}</span>
                </div>
                <h1>{{ $deal->company->legal_name }}</h1>
                <p>{{ $saleInteraction?->note ?? __('operations.detail.not_recorded') }}</p>
            </div>
            <div class="deal-hero__state">
                <span>{{ __('operations.detail.summary.status') }}</span>
                {!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}
                <small class="numeric-data">{{ __('operations.detail.fields.status_since') }} · {{ $deal->status_changed_at->format('d.m.Y H:i') }}</small>
            </div>
        </header>

        <section class="deal-progress" aria-label="{{ __('operations.detail.summary.title') }}">
            <div class="deal-progress__copy">
                <span>{{ __('operations.detail.summary.documents') }}</span>
                <strong class="numeric-data">{{ __('operations.detail.summary.document_progress', ['completed' => $documentCompleted, 'total' => $documentTotal]) }}</strong>
                <small class="{{ $documentMissing > 0 ? 'is-risk' : '' }}">{{ __('operations.detail.summary.missing', ['count' => $documentMissing]) }}</small>
            </div>
            <div class="deal-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $documentProgress }}">
                <span style="width: {{ $documentProgress }}%"></span>
            </div>
            <div class="deal-progress__meta">
                <span>{{ __('operations.detail.summary.manager') }}</span>
                <strong>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</strong>
            </div>
            <div class="deal-progress__meta">
                <span>{{ __('operations.detail.summary.updated') }}</span>
                <strong class="numeric-data">{{ $deal->updated_at->format('d.m.Y H:i') }}</strong>
            </div>
        </section>

        <div class="deal-workspace__grid">
            <main class="deal-workspace__main">
                <section class="deal-section deal-section--context">
                    <header class="deal-section__header">
                        <div>
                            <span class="operations-label">{{ __('operations.detail.workspace.context_eyebrow') }}</span>
                            <h2>{{ __('operations.detail.workspace.context') }}</h2>
                        </div>
                    </header>
                    <div class="deal-context-grid">
                        <article>
                            <span>{{ __('operations.detail.fields.contacted_person') }}</span>
                            <strong>{{ $deal->originatingLead?->primaryContact?->full_name ?? __('operations.detail.not_recorded') }}</strong>
                        </article>
                        <article>
                            <span>{{ __('operations.detail.fields.marketer') }}</span>
                            <strong>{{ $deal->originatingLead?->owner?->name ?? $deal->openedBy->name }}</strong>
                        </article>
                        <article>
                            <span>{{ __('operations.detail.fields.won_at') }}</span>
                            <strong class="numeric-data">{{ $wonHistory?->entered_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded') }}</strong>
                        </article>
                        <article>
                            <span>{{ __('operations.detail.fields.amount') }}</span>
                            <strong class="numeric-data">{{ $deal->requested_amount ? number_format((float) $deal->requested_amount, 2, ',', '.').' ₺' : __('operations.detail.not_recorded') }}</strong>
                        </article>
                    </div>
                </section>

                <section class="deal-section deal-section--documents">
                    @include('filament.pages.partials.document-checklist')
                </section>

                <section class="deal-section deal-activity" data-testid="deal-activity">
                    <header class="deal-section__header deal-activity__header">
                        <div>
                            <span class="operations-label">{{ __('operations.detail.workspace.activity_eyebrow') }}</span>
                            <h2>{{ __('operations.detail.workspace.activity') }}</h2>
                        </div>
                        <nav class="deal-activity__filters" aria-label="{{ __('operations.detail.workspace.activity') }}">
                            @foreach (['comments', 'tasks', 'interactions', 'history'] as $view)
                                <button type="button" wire:click="$set('activeTab', '{{ $view }}')" class="deal-activity__filter {{ $activeTab === $view || ($view === 'comments' && ! in_array($activeTab, ['tasks', 'interactions', 'history'], true)) ? 'is-active' : '' }}">
                                    {{ __('operations.detail.tabs.'.$view) }}
                                </button>
                            @endforeach
                        </nav>
                    </header>
                    <div class="deal-activity__body" wire:key="deal-activity-{{ $activeTab }}">
                        @if ($activeTab === 'tasks')
                            <livewire:subject-tasks subject-type="deal" :subject-id="$deal->id" :key="'deal-tasks-'.$deal->id" />
                        @elseif ($activeTab === 'interactions')
                            @include('filament.pages.partials.interactions', ['interactions' => $deal->interactions, 'contacts' => $deal->company->contacts->where('is_active', true)])
                        @elseif ($activeTab === 'history')
                            <livewire:collaboration-timeline subject-type="deal" :subject-id="$deal->id" :key="'deal-timeline-'.$deal->id" />
                        @else
                            <livewire:collaboration-comments subject-type="deal" :subject-id="$deal->id" :key="'deal-comments-'.$deal->id" />
                        @endif
                    </div>
                </section>
            </main>

            <aside class="deal-workspace__sidebar" aria-label="{{ __('operations.detail.workspace.details') }}">
                @if (is_array($serviceWorkflow) && filled($serviceWorkflow['steps'] ?? null))
                    <section class="deal-side-panel deal-side-panel--guide" data-testid="service-workflow-guide">
                        <header>
                            <span class="operations-label">{{ __('operations.detail.workspace.guide_eyebrow') }}</span>
                            <h2>{{ $serviceWorkflow['name'] ?? __('operations.detail.workspace.guide') }}</h2>
                            @if (filled($serviceWorkflow['description'] ?? null))
                                <p>{{ $serviceWorkflow['description'] }}</p>
                            @endif
                        </header>
                        <ol class="service-workflow-guide">
                            @foreach ($serviceWorkflow['steps'] as $step)
                                <li data-step-type="{{ $step['type'] ?? 'action' }}">
                                    <span class="service-workflow-guide__index numeric-data">{{ $loop->iteration }}</span>
                                    <div>
                                        <small>{{ __('management.workflow_step_types.'.($step['type'] ?? 'action')) }}</small>
                                        <strong>{{ $step['title'] ?? '' }}</strong>
                                        <p>{{ $step['guidance'] ?? '' }}</p>
                                        @if (filled($step['attention_note'] ?? null))
                                            <aside>{{ __('operations.detail.workspace.attention') }} · {{ $step['attention_note'] }}</aside>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <section class="deal-side-panel deal-side-panel--process">
                    <header>
                        <span class="operations-label">{{ __('operations.detail.workspace.process_eyebrow') }}</span>
                        <h2>{{ __('operations.detail.workspace.process') }}</h2>
                    </header>
                    <div class="deal-side-panel__actions">
                        @forelse ($transitions as $transition)
                            @if ($transition->required_permission === 'deal.assign' && $deal->pm_user_id === null)
                                <label>
                                    {{ __('operations.assignment.manager') }}
                                    <select wire:model="projectManagerId" required>
                                        <option value="">{{ __('operations.assignment.choose_manager') }}</option>
                                        @foreach ($projectManagers as $projectManager)
                                            <option value="{{ $projectManager->id }}">{{ $projectManager->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <button type="button" wire:click="assignProjectManager({{ $transition->to_status_id }})" class="operations-button operations-button--primary">
                                    {{ __('operations.assignment.assign') }}
                                </button>
                            @else
                                <button type="button" wire:click="transitionDeal({{ $transition->to_status_id }})" class="deal-transition-action">
                                    <span>{{ $transition->toStatus->label }}</span><span aria-hidden="true">→</span>
                                </button>
                            @endif
                        @empty
                            <p class="operations-muted">{{ __('operations.detail.no_transitions') }}</p>
                        @endforelse
                    </div>
                    @if ($transitionError)
                        <div class="operations-error" role="alert" data-testid="transition-error">
                            <strong>{{ __('operations.detail.transition_error') }}</strong>
                            <span>{{ $transitionError }}</span>
                        </div>
                    @endif
                </section>

                <section class="deal-side-panel">
                    <header><h2>{{ __('operations.detail.workspace.details') }}</h2></header>
                    <dl class="deal-details-list">
                        @foreach ([
                            __('operations.detail.fields.reference') => $deal->reference_no,
                            __('operations.detail.fields.program') => $deal->programVersion->program->name.' · '.$deal->programVersion->call_period,
                            __('operations.detail.fields.manager') => $deal->projectManager?->name ?? __('operations.board.unassigned'),
                            __('operations.detail.fields.priority') => $deal->priority,
                            __('operations.detail.fields.application_no') => $deal->application_no ?? __('operations.detail.not_recorded'),
                            __('operations.detail.fields.applied_at') => $deal->applied_at?->format('d.m.Y') ?? __('operations.detail.not_recorded'),
                            __('operations.detail.fields.decided_at') => $deal->decided_at?->format('d.m.Y') ?? __('operations.detail.not_recorded'),
                            __('operations.detail.fields.document_requested_at') => $deal->document_requested_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
                            __('operations.detail.fields.first_document_received_at') => $deal->first_document_received_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded'),
                        ] as $label => $value)
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                <section class="deal-side-panel">
                    <header><h2>{{ __('operations.detail.tabs.team') }}</h2></header>
                    <div class="deal-team-list" data-testid="deal-team">
                        <article><span class="deal-team-avatar">{{ \App\Filament\Support\CollaborationView::initials($deal->projectManager?->name ?? '?') }}</span><div><strong>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</strong><small>{{ __('operations.detail.team.manager') }}</small></div></article>
                        <article><span class="deal-team-avatar">{{ \App\Filament\Support\CollaborationView::initials($deal->openedBy->name) }}</span><div><strong>{{ $deal->openedBy->name }}</strong><small>{{ __('operations.detail.team.opened_by') }}</small></div></article>
                        @foreach ($teamMembers->take(4) as $member)
                            <article><span class="deal-team-avatar">{{ \App\Filament\Support\CollaborationView::initials($member->name) }}</span><div><strong>{{ $member->name }}</strong><small>{{ __('operations.detail.team.member') }}</small></div></article>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
