<x-filament-panels::page>
    @php($company = $this->record->load([
        'owner',
        'contacts.communicationConsents' => fn ($query) => $query->where('channel', 'call')->where('purpose', 'marketing')->latest('effective_from'),
        'leads' => fn ($query) => $query->with(['status', 'owner', 'interestedProgramVersion.program', 'primaryContact'])->latest(),
        'deals' => fn ($query) => $query->with(['status', 'projectManager', 'programVersion.program'])->latest(),
    ]))
    <div class="company-detail" data-testid="company-contact-cards">
        <nav class="deal-tabs" aria-label="{{ $company->legal_name }}">
            @foreach (['overview', 'contacts', 'opportunities', 'projects', 'comments', 'tasks', 'history'] as $tab)
                <button type="button" wire:click="$set('activeTab', '{{ $tab }}')" class="deal-tab {{ $activeTab === $tab ? 'deal-tab--active' : '' }}">{{ __('marketing.company.tabs.'.$tab) }}</button>
            @endforeach
        </nav>

        @if ($activeTab === 'overview')
        <section class="operations-panel operations-facts">
            <div class="operations-fact"><dt>{{ __('panel.fields.legal_name') }}</dt><dd>{{ $company->legal_name }}</dd></div>
            <div class="operations-fact"><dt>{{ __('panel.fields.industry') }}</dt><dd>{{ __('panel.industries.'.$company->industry) }}</dd></div>
            <div class="operations-fact"><dt>{{ __('panel.fields.city') }}</dt><dd>{{ $company->city }}</dd></div>
            <div class="operations-fact"><dt>{{ __('panel.fields.owner') }}</dt><dd>{{ $company->owner?->name ?? '—' }}</dd></div>
            <div class="operations-fact"><dt>{{ __('marketing.company.open_opportunities') }}</dt><dd class="numeric-data">{{ $company->leads->whereNull('converted_deal_id')->count() }}</dd></div>
            <div class="operations-fact"><dt>{{ __('marketing.company.projects') }}</dt><dd class="numeric-data">{{ $company->deals->count() }}</dd></div>
        </section>
        @elseif ($activeTab === 'contacts')
        <section class="contact-grid">
            @foreach ($company->contacts as $contact)
                @php($consent = $contact->communicationConsents->first())
                @php($rejection = $contact->communicationConsents->first(fn ($item) => in_array($item->status, ['denied', 'withdrawn'], true)))
                <article class="operations-panel contact-card">
                    <header><strong>{{ $contact->full_name }}</strong><span>{{ $contact->title }}</span></header>
                    <div class="numeric-data">{{ $contact->phone ?? __('marketing.calls.no_phone') }}</div>
                    <div>{{ __('marketing.consent.disclosure') }}: {{ $consent?->disclosure_date?->format('d.m.Y') ?? __('marketing.consent.not_recorded') }}</div>
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
                <label>{{ __('marketing.contacts.call_consent') }}
                    <select wire:model="contactCallConsent">@foreach (__('marketing.contacts.consent_options') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                </label>
                <label>{{ __('marketing.contacts.disclosure_date') }}<input type="date" wire:model="contactDisclosureDate"></label>
                <button class="operations-button operations-button--primary" type="submit">{{ __('marketing.contacts.save') }}</button>
            </form>
        </section>
        @elseif ($activeTab === 'opportunities')
            <section class="checklist-table-wrap">
                <table class="checklist-table"><thead><tr><th>{{ __('marketing.detail.program') }}</th><th>{{ __('marketing.detail.status') }}</th><th>{{ __('marketing.detail.owner') }}</th><th>{{ __('marketing.intake.contact.title') }}</th><th>{{ __('marketing.board.last_interaction') }}</th></tr></thead><tbody>
                @forelse($company->leads as $lead)<tr><td><a class="operations-link" href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}">{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</a></td><td>{!! \App\Filament\Support\StatusBadge::make($lead->status->color, $lead->status->label) !!}</td><td>{{ $lead->owner->name }}</td><td>{{ $lead->primaryContact?->full_name ?? __('marketing.consent.not_recorded') }}</td><td class="numeric-data">{{ $lead->updated_at->format('d.m.Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="operations-placeholder">{{ __('marketing.company.no_opportunities') }}</td></tr>@endforelse
                </tbody></table>
            </section>
        @elseif ($activeTab === 'projects')
            <section class="checklist-table-wrap">
                <table class="checklist-table"><thead><tr><th>{{ __('operations.detail.fields.reference') }}</th><th>{{ __('marketing.detail.program') }}</th><th>{{ __('marketing.detail.status') }}</th><th>{{ __('operations.detail.fields.manager') }}</th><th>{{ __('marketing.company.opened_at') }}</th></tr></thead><tbody>
                @forelse($company->deals as $deal)<tr><td><a class="operations-link numeric-data" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $deal->id]) }}">{{ $deal->reference_no }}</a></td><td>{{ $deal->programVersion->program->name }}</td><td>{!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}</td><td>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</td><td class="numeric-data">{{ $deal->created_at->format('d.m.Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="operations-placeholder">{{ __('marketing.company.no_projects') }}</td></tr>@endforelse
                </tbody></table>
            </section>
        @elseif ($activeTab === 'comments')
            <livewire:collaboration-comments subject-type="company" :subject-id="$company->id" :key="'company-comments-'.$company->id" />
        @elseif ($activeTab === 'tasks')
            <livewire:subject-tasks subject-type="company" :subject-id="$company->id" :key="'company-tasks-'.$company->id" />
        @elseif ($activeTab === 'history')
            <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" :key="'company-timeline-'.$company->id" />
        @endif
    </div>
</x-filament-panels::page>
