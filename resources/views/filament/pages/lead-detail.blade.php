<x-filament-panels::page>
    <div class="lead-detail" data-testid="lead-detail">
        <section class="operations-panel operations-facts">
            <div class="operations-fact"><dt>{{ __('marketing.detail.company') }}</dt><dd>{{ $lead->company->legal_name }}</dd></div>
            <div class="operations-fact"><dt>{{ __('marketing.detail.owner') }}</dt><dd>{{ $lead->owner->name }}</dd></div>
            <div class="operations-fact"><dt>{{ __('marketing.detail.status') }}</dt><dd>{!! \App\Filament\Support\StatusBadge::make($lead->status->color, $lead->status->label) !!}</dd></div>
            <div class="operations-fact"><dt>{{ __('marketing.detail.program') }}</dt><dd>{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</dd></div>
            @if ($lead->convertedDeal)<div class="operations-fact"><dt>{{ __('marketing.detail.converted_deal') }}</dt><dd><a class="operations-link numeric-data" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $lead->convertedDeal->id]) }}">{{ $lead->convertedDeal->reference_no }}</a></dd></div>@endif
        </section>

        <section class="contact-grid">
            @foreach ($lead->company->contacts as $contact)
                @php($consent = $contact->communicationConsents->first())
                @php($rejection = $contact->communicationConsents->first(fn ($item) => in_array($item->status, ['denied', 'withdrawn'], true)))
                <article class="operations-panel contact-card">
                    <header><strong>{{ $contact->full_name }}</strong><span>{{ $contact->title }}</span></header>
                    <div class="numeric-data">{{ $contact->phone ?? __('marketing.calls.no_phone') }}</div>
                    <div>{{ __('marketing.consent.source') }}: {{ $contact->data_source }}</div>
                    <div>{{ __('marketing.consent.disclosure') }}: {{ $consent?->disclosure_date?->format('d.m.Y') ?? __('marketing.consent.not_recorded') }} · {{ $consent?->disclosure_method ?? __('marketing.consent.not_recorded') }}</div>
                    <div class="consent-strip {{ $contact->consent_call === true && ! $contact->do_not_call ? 'consent-strip--allowed' : 'consent-strip--blocked' }}">
                        {{ $contact->consent_call === true && ! $contact->do_not_call ? __('marketing.consent.call_allowed') : __('marketing.consent.call_not_allowed') }}
                        @if ($rejection)<span class="numeric-data">{{ __('marketing.consent.rejected_at') }}: {{ $rejection->effective_from->format('d.m.Y H:i') }}</span>@endif
                    </div>
                    @if ($contact->do_not_call)
                        <div class="call-blocked">{{ __('marketing.calls.blocked_reason') }}</div>
                    @else
                        <div class="operations-actions">
                            @if ($contact->phone)<a class="operations-button operations-button--primary numeric-data" href="tel:{{ $contact->phone }}">{{ __('marketing.calls.call') }}</a>@endif
                            <button class="operations-button" type="button" wire:click="doNotCall({{ $contact->id }})">{{ __('marketing.consent.do_not_call') }}</button>
                        </div>
                    @endif
                </article>
            @endforeach
        </section>

        @include('filament.pages.partials.interactions', ['interactions' => $lead->interactions])
    </div>
</x-filament-panels::page>
