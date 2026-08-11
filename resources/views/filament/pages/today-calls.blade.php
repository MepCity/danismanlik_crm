<x-filament-panels::page>
    <div class="call-list" data-testid="today-calls">
        @forelse ($leads as $lead)
            @php($contact = $lead->company->contacts->first())
            @php($latestConsent = $contact?->communicationConsents->first())
            @php($rejection = $contact?->communicationConsents->first(fn ($consent) => in_array($consent->status, ['denied', 'withdrawn'], true)))
            <article class="call-card {{ $lead->next_call_at->isBefore(today()) ? 'call-card--overdue' : '' }}" data-testid="call-card-{{ $lead->id }}">
                <header class="call-card__header">
                    <div>
                        <a class="call-card__company" href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}">{{ $lead->company->legal_name }}</a>
                        <div class="operations-muted">{{ $contact?->full_name ?? __('marketing.calls.no_contact') }}</div>
                    </div>
                    <div class="call-card__due numeric-data">
                        {{ $lead->next_call_at->isBefore(today()) ? __('marketing.calls.overdue') : __('marketing.calls.today') }} · {{ $lead->next_call_at->format('d.m.Y H:i') }}
                    </div>
                </header>

                <div class="call-card__facts">
                    <span class="numeric-data">{{ $contact?->phone ?? __('marketing.calls.no_phone') }}</span>
                    <span>{{ __('marketing.calls.last_outcome') }}: {{ $lead->interactions->first()?->outcome ? __('marketing.interactions.outcomes.'.$lead->interactions->first()->outcome) : __('marketing.calls.no_interaction') }}</span>
                    <span class="numeric-data">{{ __('marketing.calls.call_number', ['count' => $lead->interactions_count + 1]) }}</span>
                </div>

                <div class="consent-strip {{ $contact?->consent_call === true && ! $contact?->do_not_call ? 'consent-strip--allowed' : 'consent-strip--blocked' }}">
                    <strong>{{ $contact?->consent_call === true && ! $contact?->do_not_call ? __('marketing.consent.call_allowed') : __('marketing.consent.call_not_allowed') }}</strong>
                    <span>{{ __('marketing.consent.source') }}: {{ $contact?->data_source ?? __('marketing.consent.unknown') }}</span>
                    <span>{{ __('marketing.consent.disclosure') }}: {{ $latestConsent?->disclosure_date?->format('d.m.Y') ?? __('marketing.consent.not_recorded') }} · {{ $latestConsent?->disclosure_method ?? __('marketing.consent.not_recorded') }}</span>
                    @if ($rejection)<span class="numeric-data">{{ __('marketing.consent.rejected_at') }}: {{ $rejection->effective_from->format('d.m.Y H:i') }}</span>@endif
                </div>

                <div class="call-card__actions">
                    @if ($contact?->do_not_call)
                        <div class="call-blocked" role="status" data-testid="call-blocked">{{ __('marketing.calls.blocked_reason') }}</div>
                    @elseif ($contact?->phone)
                        <a class="operations-button operations-button--primary call-link numeric-data" href="tel:{{ $contact->phone }}">{{ __('marketing.calls.call') }}</a>
                    @else
                        <span class="operations-muted">{{ __('marketing.calls.no_phone') }}</span>
                    @endif

                    @unless ($contact?->do_not_call)
                        <button type="button" class="operations-button" wire:click="doNotCall({{ $contact?->id ?? 0 }})" @disabled($contact === null)>{{ __('marketing.consent.do_not_call') }}</button>
                    @endunless
                </div>

                <div class="quick-results" aria-label="{{ __('marketing.interactions.quick_result') }}">
                    @foreach ($outcomes as $value => $label)
                        <button type="button" class="quick-result {{ $activeLeadId === $lead->id && $quickOutcome === $value ? 'quick-result--selected' : '' }}" wire:click="chooseOutcome({{ $lead->id }}, '{{ $value }}')">{{ $label }}</button>
                    @endforeach
                </div>

                @if ($activeLeadId === $lead->id)
                    <form wire:submit="saveOutcome" class="quick-note">
                        <label for="quick-note-{{ $lead->id }}">{{ __('marketing.interactions.note_optional') }}</label>
                        <textarea id="quick-note-{{ $lead->id }}" wire:model="quickNote" rows="2"></textarea>
                        <button class="operations-button operations-button--primary" type="submit">{{ __('marketing.interactions.save_result') }}</button>
                    </form>
                @endif
            </article>
        @empty
            <section class="operations-panel operations-placeholder">{{ __('marketing.calls.empty') }}</section>
        @endforelse
    </div>
</x-filament-panels::page>
