<section class="operations-panel interactions-panel" data-testid="interactions">
    <header><h3>{{ __('marketing.interactions.title') }}</h3><span class="numeric-data">{{ $interactions->count() }}</span></header>
    <form wire:submit="addInteraction" class="interaction-form operations-inline-form">
        @if(isset($contacts))
            <label>{{ __('marketing.interactions.contact') }}
                <select wire:model="interactionContactId" required><option value="">{{ __('marketing.intake.choose') }}</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->full_name }} · {{ $contact->title }}</option>@endforeach</select>
            </label>
        @endif
        <label>{{ __('marketing.interactions.type') }}
            <select wire:model="interactionType">
                @foreach (__('marketing.interactions.types') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </label>
        <label>{{ __('marketing.interactions.date') }}<input type="datetime-local" wire:model="interactionOccurredAt" required></label>
        <label>{{ __('marketing.interactions.duration') }}<input class="numeric-data" type="number" min="0" wire:model="interactionDuration"></label>
        <label>{{ __('marketing.interactions.outcome') }}<input type="text" wire:model="interactionOutcome"></label>
        <label class="interaction-form__note">{{ __('marketing.interactions.note') }}<textarea wire:model="interactionNote" rows="2"></textarea></label>
        <button type="submit" class="operations-button operations-button--primary">{{ __('marketing.interactions.add') }}</button>
    </form>
    <div class="interaction-list">
        @forelse ($interactions as $interaction)
            <article class="interaction-row">
                <span class="interaction-row__shape" aria-hidden="true">{{ match($interaction->type) {'call' => '☎', 'meeting' => '◇', 'email' => '✉', default => '•'} }}</span>
                <strong>{{ $interaction->type === 'call' && $interaction->direction === 'inbound' ? __('marketing.interactions.types.incoming_call') : __('marketing.interactions.types.'.$interaction->type) }}</strong>
                <span>{{ $interaction->contact?->full_name ?? __('marketing.consent.not_recorded') }}</span>
                <span>{{ $interaction->outcome ? (__('marketing.interactions.outcomes.'.$interaction->outcome) !== 'marketing.interactions.outcomes.'.$interaction->outcome ? __('marketing.interactions.outcomes.'.$interaction->outcome) : $interaction->outcome) : '—' }}</span>
                <span>{{ $interaction->note }}</span>
                <span class="numeric-data">{{ $interaction->occurred_at->format('d.m.Y H:i') }}@if ($interaction->duration_minutes) · {{ __('marketing.interactions.minutes', ['count' => $interaction->duration_minutes]) }}@endif</span>
                <span>{{ $interaction->user->name }}</span>
            </article>
        @empty
            <div class="operations-placeholder">{{ __('marketing.interactions.empty') }}</div>
        @endforelse
    </div>
</section>
