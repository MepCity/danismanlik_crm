<x-filament-panels::page>
    @php($company = $this->record->load(['contacts.communicationConsents' => fn ($query) => $query->where('channel', 'call')->where('purpose', 'marketing')->latest('effective_from')]))
    <div class="company-detail" data-testid="company-contact-cards">
        <section class="operations-panel operations-facts">
            <div class="operations-fact"><dt>{{ __('panel.fields.legal_name') }}</dt><dd>{{ $company->legal_name }}</dd></div>
            <div class="operations-fact"><dt>{{ __('panel.fields.city') }}</dt><dd class="numeric-data">{{ $company->city }}</dd></div>
        </section>

        <section class="contact-grid">
            @foreach ($company->contacts as $contact)
                @php($consent = $contact->communicationConsents->first())
                @php($rejection = $contact->communicationConsents->first(fn ($item) => in_array($item->status, ['denied', 'withdrawn'], true)))
                <article class="operations-panel contact-card">
                    <header><strong>{{ $contact->full_name }}</strong><span>{{ $contact->title }}</span></header>
                    <div class="numeric-data">{{ $contact->phone ?? __('marketing.calls.no_phone') }}</div>
                    <div>{{ __('marketing.consent.source') }}: {{ $contact->data_source }}</div>
                    <div>{{ __('marketing.consent.disclosure') }}: {{ $consent?->disclosure_date?->format('d.m.Y') ?? __('marketing.consent.not_recorded') }} · {{ $consent?->disclosure_method ?? __('marketing.consent.not_recorded') }}</div>
                    <div class="consent-strip {{ $contact->consent_call === true && ! $contact->do_not_call ? 'consent-strip--allowed' : 'consent-strip--blocked' }}">
                        <strong>{{ $contact->consent_call === true && ! $contact->do_not_call ? __('marketing.consent.call_allowed') : __('marketing.consent.call_not_allowed') }}</strong>
                        @if ($rejection)<span class="numeric-data">{{ __('marketing.consent.rejected_at') }}: {{ $rejection->effective_from->format('d.m.Y H:i') }}</span>@endif
                    </div>
                    @if ($contact->do_not_call)
                        <div class="call-blocked">{{ __('marketing.calls.blocked_reason') }}</div>
                    @else
                        <button class="operations-button" type="button" wire:click="doNotCall({{ $contact->id }})">{{ __('marketing.consent.do_not_call') }}</button>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="operations-panel contact-create">
            <h3>{{ __('marketing.contacts.add') }}</h3>
            <form wire:submit="addContact" class="operations-inline-form contact-form">
                <label>{{ __('marketing.contacts.full_name') }}<input wire:model="contactFullName" required></label>
                <label>{{ __('marketing.contacts.title') }}<input wire:model="contactTitle"></label>
                <label>{{ __('marketing.contacts.phone') }}<input class="numeric-data" wire:model="contactPhone"></label>
                <label>{{ __('marketing.contacts.email') }}<input type="email" wire:model="contactEmail"></label>
                <label>{{ __('marketing.contacts.data_source') }}
                    <select wire:model="contactDataSource" required>
                        <option value="">{{ __('marketing.contacts.choose_source') }}</option>
                        @foreach (__('marketing.contacts.sources') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                @error('contactDataSource')<span class="operations-error">{{ $message }}</span>@enderror
                <label>{{ __('marketing.contacts.call_consent') }}
                    <select wire:model="contactCallConsent">@foreach (__('marketing.contacts.consent_options') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                </label>
                <label>{{ __('marketing.contacts.disclosure_date') }}<input type="date" wire:model="contactDisclosureDate"></label>
                <label>{{ __('marketing.contacts.disclosure_method') }}<input wire:model="contactDisclosureMethod"></label>
                <button class="operations-button operations-button--primary" type="submit">{{ __('marketing.contacts.save') }}</button>
            </form>
        </section>
    </div>
</x-filament-panels::page>
