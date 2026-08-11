<x-filament-panels::page>
    <div class="deal-board lead-board" data-testid="lead-board">
        @foreach ($statuses as $status)
            @php($leads = $leadsByStatus->get($status->id, collect()))
            <section class="deal-column" aria-labelledby="lead-status-{{ $status->id }}">
                <header class="deal-column__header">
                    <span id="lead-status-{{ $status->id }}">{!! \App\Filament\Support\StatusBadge::make($status->color, $status->label) !!}</span>
                    <span class="numeric-data">{{ $leads->count() }}</span>
                </header>
                <div class="deal-column__cards">
                    @forelse ($leads as $lead)
                        @php($lastInteraction = $lead->interactions->first())
                        <article class="deal-card lead-card">
                            <a href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}" class="deal-card__title">{{ $lead->company->legal_name }}</a>
                            <div class="deal-card__meta">
                                <span>{{ $lead->owner->name }}</span>
                                <span>{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</span>
                            </div>
                            <div class="operations-muted">{{ __('marketing.board.last_interaction') }}: {{ $lastInteraction?->occurred_at?->format('d.m.Y H:i') ?? __('marketing.board.none') }}</div>
                            <div class="deal-card__age numeric-data">{{ __('marketing.board.waiting_days', ['count' => $lead->created_at->startOfDay()->diffInDays(today())]) }}</div>
                            @if (! $lead->status->is_terminal)
                                <div class="lead-card__transitions">
                                    @foreach ($lead->status->outgoingTransitions->where('is_active', true) as $transition)
                                        <button type="button" class="operations-link" wire:click="beginTransition({{ $lead->id }}, {{ $transition->to_status_id }})">{{ $transition->toStatus->label }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="deal-column__empty">{{ __('marketing.board.empty_column') }}</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    @if ($transitionLeadId && $selectedTarget)
        <section class="operations-panel transition-drawer" data-testid="lead-transition-form">
            <header>
                <div>
                    <div class="operations-label">{{ __('marketing.transition.target') }}</div>
                    {!! \App\Filament\Support\StatusBadge::make($selectedTarget->color, $selectedTarget->label) !!}
                </div>
                <button type="button" class="operations-link" wire:click="cancelTransition">{{ __('marketing.transition.cancel') }}</button>
            </header>

            <form wire:submit="saveTransition" class="operations-inline-form">
                @if (in_array('next_call_at', $selectedTarget->required_fields, true))
                    <label>{{ __('marketing.transition.next_call_at') }}<input type="datetime-local" wire:model="nextCallAt" required></label>
                    @error('nextCallAt')<span class="operations-error">{{ $message }}</span>@enderror
                @endif
                @if (in_array('owner_user_id', $selectedTarget->required_fields, true))
                    <label>{{ __('marketing.transition.owner') }}
                        <select wire:model="ownerUserId" required>@foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select>
                    </label>
                    @error('ownerUserId')<span class="operations-error">{{ $message }}</span>@enderror
                @endif
                @if (in_array('lost_reason', $selectedTarget->required_fields, true))
                    <label>{{ __('marketing.transition.lost_reason') }}<textarea wire:model="lostReason" required></textarea></label>
                    @error('lostReason')<span class="operations-error">{{ $message }}</span>@enderror
                @endif
                @if (in_array('program_version_id', $selectedTarget->required_fields, true))
                    <label>{{ __('marketing.transition.program_version') }}
                        <select wire:model="programVersionId" required>
                            <option value="">{{ __('marketing.transition.choose_program') }}</option>
                            @foreach ($programVersions as $version)<option value="{{ $version->id }}">{{ $version->program->name }} · {{ $version->call_period }}</option>@endforeach
                        </select>
                    </label>
                    @error('programVersionId')<span class="operations-error">{{ $message }}</span>@enderror
                    <div class="conversion-chain">{{ __('marketing.conversion.chain') }}</div>
                @endif
                @if ($transitionError)<div class="operations-error" role="alert">{{ $transitionError }}</div>@endif
                <button class="operations-button operations-button--primary" type="submit">{{ __('marketing.transition.save') }}</button>
            </form>
        </section>
    @endif
</x-filament-panels::page>
