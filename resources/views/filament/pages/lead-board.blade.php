<x-filament-panels::page>
    <div class="pipeline-workspace" x-data="{ draggedLead: null }" data-testid="lead-board">
        <header class="pipeline-toolbar">
            <div>
                <span class="operations-label">{{ __('marketing.board.eyebrow') }}</span>
                <p>{{ __('marketing.board.description') }}</p>
            </div>
            <div class="pipeline-toolbar__hint"><span aria-hidden="true">↔</span>{{ __('marketing.board.drag_hint') }}</div>
        </header>

        <div class="pipeline-board" aria-label="{{ __('marketing.board.title') }}">
            @foreach ($statuses as $status)
                @php($leads = $leadsByStatus->get($status->id, collect()))
                <section class="pipeline-stage" aria-labelledby="lead-status-{{ $status->id }}"
                    @dragover.prevent @dragenter.prevent="$el.classList.add('pipeline-stage--target')"
                    @dragleave="$el.classList.remove('pipeline-stage--target')"
                    @drop.prevent="$el.classList.remove('pipeline-stage--target'); if (draggedLead) { $wire.moveLead(draggedLead, {{ $status->id }}); draggedLead = null }">
                    <header class="pipeline-stage__header">
                        <span id="lead-status-{{ $status->id }}">{!! \App\Filament\Support\StatusBadge::make($status->color, $status->label) !!}</span>
                        <span class="pipeline-stage__count numeric-data">{{ $leads->count() }}</span>
                    </header>
                    <div class="pipeline-stage__cards">
                        @forelse ($leads as $lead)
                            @php($lastInteraction = $lead->interactions->first())
                            <article class="pipeline-card" draggable="true" tabindex="0" role="button"
                                aria-label="{{ __('marketing.board.open_detail', ['company' => $lead->company->legal_name]) }}"
                                @dragstart="draggedLead = {{ $lead->id }}; $el.classList.add('pipeline-card--dragging')"
                                @dragend="draggedLead = null; $el.classList.remove('pipeline-card--dragging')"
                                @click="$wire.openLead({{ $lead->id }})" @keydown.enter.prevent="$wire.openLead({{ $lead->id }})">
                                <div class="pipeline-card__topline">
                                    <span class="pipeline-card__company">{{ $lead->company->legal_name }}</span>
                                    <span class="pipeline-card__grip" aria-hidden="true">⠿</span>
                                </div>
                                <div class="pipeline-card__program">{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</div>
                                <div class="pipeline-card__footer">
                                    <span>{{ $lead->owner->name }}</span>
                                    <span class="numeric-data">{{ $lastInteraction?->occurred_at?->format('d.m') ?? '—' }}</span>
                                </div>
                                @if($lead->next_call_at)
                                    <div class="pipeline-card__activity {{ $lead->next_call_at->isPast() ? 'pipeline-card__activity--urgent' : '' }}">
                                        <span aria-hidden="true">◷</span>{{ $lead->next_call_at->diffForHumans() }}
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="pipeline-stage__empty">{{ __('marketing.board.empty_column') }}</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    @if ($selectedLead)
        <div class="record-peek-backdrop" wire:click="closeLead"></div>
        <aside class="record-peek" aria-label="{{ __('marketing.board.detail_title') }}" data-testid="lead-detail-drawer">
            <header class="record-peek__header">
                <div><span class="operations-label">{{ __('marketing.board.detail_title') }}</span><h2>{{ $selectedLead->company->legal_name }}</h2></div>
                <button type="button" class="record-peek__close" wire:click="closeLead" aria-label="{{ __('marketing.transition.cancel') }}">×</button>
            </header>
            <div class="record-peek__status">{!! \App\Filament\Support\StatusBadge::make($selectedLead->status->color, $selectedLead->status->label) !!}</div>
            <dl class="record-peek__facts">
                <div><dt>{{ __('marketing.detail.program') }}</dt><dd>{{ $selectedLead->interestedProgramVersion?->program?->name ?? '—' }}</dd></div>
                <div><dt>{{ __('marketing.transition.owner') }}</dt><dd>{{ $selectedLead->owner->name }}</dd></div>
                <div><dt>{{ __('marketing.intake.contact.title') }}</dt><dd>{{ $selectedLead->company->contacts->first()?->full_name ?? '—' }}</dd></div>
                <div><dt>{{ __('panel.fields.city') }}</dt><dd>{{ $selectedLead->company->city }}</dd></div>
            </dl>
            <section class="record-peek__activity"><span class="operations-label">{{ __('marketing.board.last_interaction') }}</span><p>{{ $selectedLead->interactions->first()?->note ?? __('marketing.board.none') }}</p></section>
            <a class="operations-button operations-button--primary" href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $selectedLead->id]) }}">{{ __('marketing.board.open_full_detail') }}</a>
        </aside>
    @endif

    @if ($transitionLeadId && $selectedTarget)
        <section class="operations-panel transition-drawer" data-testid="lead-transition-form">
            <header><div><div class="operations-label">{{ __('marketing.transition.target') }}</div>{!! \App\Filament\Support\StatusBadge::make($selectedTarget->color, $selectedTarget->label) !!}</div><button type="button" class="operations-link" wire:click="cancelTransition">{{ __('marketing.transition.cancel') }}</button></header>
            <form wire:submit="saveTransition" class="operations-inline-form">
                @if (in_array('next_call_at', $selectedTarget->required_fields, true))<label>{{ __('marketing.transition.next_call_at') }}<input type="datetime-local" wire:model="nextCallAt" required></label>@endif
                @if (in_array('owner_user_id', $selectedTarget->required_fields, true))<label>{{ __('marketing.transition.owner') }}<select wire:model="ownerUserId" required>@foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></label>@endif
                @if (in_array('lost_reason', $selectedTarget->required_fields, true))<label>{{ __('marketing.transition.lost_reason') }}<textarea wire:model="lostReason" required></textarea></label>@endif
                @if (in_array('program_version_id', $selectedTarget->required_fields, true))<label>{{ __('marketing.transition.program_version') }}<select wire:model="programVersionId" required><option value="">{{ __('marketing.transition.choose_program') }}</option>@foreach ($programVersions as $version)<option value="{{ $version->id }}">{{ $version->program->name }} · {{ $version->call_period }}</option>@endforeach</select></label>@endif
                @if ($transitionError)<div class="operations-error" role="alert">{{ $transitionError }}</div>@endif
                <button class="operations-button operations-button--primary" type="submit">{{ __('marketing.transition.save') }}</button>
            </form>
        </section>
    @endif
</x-filament-panels::page>
