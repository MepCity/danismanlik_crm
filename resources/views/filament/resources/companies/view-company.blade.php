<x-filament-panels::page>
    @php($company = $this->record->load([
        'owner',
        'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('full_name'),
        'leads' => fn ($query) => $query->with(['status', 'owner', 'interestedProgramVersion.program', 'primaryContact'])->latest(),
        'deals' => fn ($query) => $query->with(['status', 'projectManager', 'programVersion.program'])->latest(),
        'tasks' => fn ($query) => $query->with('assignee')->latest(),
    ]))

    @php($contactsCount = $company->contacts->count())
    @php($leadsCount = $company->leads->count())
    @php($openLeadsCount = $company->leads->whereNull('converted_deal_id')->count())
    @php($dealsCount = $company->deals->count())
    @php($tasksCount = $company->tasks->count())

    <div class="company-workspace" data-testid="company-workspace">
        <main class="company-workspace__main">
            {{-- BÖLÜM 1: KİŞİLER --}}
            <section
                x-data="{
                    open: localStorage.getItem('crm_sec_company_contacts_{{ $company->id }}') !== 'false',
                    toggle() {
                        this.open = !this.open;
                        localStorage.setItem('crm_sec_company_contacts_{{ $company->id }}', this.open);
                    }
                }"
                class="company-section"
                :class="{ 'is-collapsed': !open, 'company-section--empty': {{ $contactsCount === 0 ? 'true' : 'false' }} }"
                data-section="contacts"
                data-testid="company-contacts-section"
            >
                <header class="company-section__header">
                    <button
                        type="button"
                        @click="toggle"
                        :aria-expanded="open.toString()"
                        class="company-section__trigger"
                        aria-controls="company-section-contacts-body"
                    >
                        <svg class="company-section__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="company-section__title">{{ __('marketing.company.tabs.contacts') }}</span>
                        <span class="company-section__count {{ $contactsCount === 0 ? 'company-section__count--zero' : '' }} numeric-data">{{ $contactsCount }}</span>
                    </button>
                </header>

                <div x-show="open" id="company-section-contacts-body" class="company-section__body">
                    <div class="company-contact-list" data-testid="company-contact-cards">
                        @forelse ($company->contacts as $contact)
                            <article class="company-contact-card">
                                <div class="company-contact-card__identity">
                                    <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($contact->full_name) }}</span>
                                    <div>
                                        <div class="company-contact-card__name">
                                            <strong>{{ $contact->full_name }}</strong>
                                            @if ($contact->is_primary)
                                                <span class="contact-primary-badge">{{ __('panel.fields.primary') }}</span>
                                            @endif
                                        </div>
                                        <span class="operations-muted">{{ $contact->title ?: __('marketing.consent.not_recorded') }}</span>
                                    </div>
                                </div>
                                <div class="company-contact-card__channels">
                                    @if ($contact->phone)
                                        <a class="operations-link numeric-data" href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a>
                                    @else
                                        <span class="operations-muted">{{ __('marketing.calls.no_phone') }}</span>
                                    @endif
                                    @if ($contact->email)
                                        <a class="operations-link" href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                    @endif
                                </div>
                                <div class="company-contact-card__consent">
                                    <span class="operations-label">{{ __('marketing.contacts.email_consent') }}:</span>
                                    @if ($contact->consent_email === true)
                                        <span class="status-token" data-status="success">
                                            <span class="status-token__shape">✓</span>
                                            {{ __('marketing.contacts.consent_options.granted') }}
                                        </span>
                                    @elseif ($contact->consent_email === false)
                                        <span class="status-token" data-status="danger">
                                            <span class="status-token__shape">✕</span>
                                            {{ __('marketing.contacts.consent_options.denied') }}
                                        </span>
                                    @else
                                        <span class="status-token" data-status="neutral">
                                            <span class="status-token__shape">?</span>
                                            {{ __('marketing.contacts.consent_options.unknown') }}
                                        </span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="operations-placeholder">{{ __('marketing.detail.contacts.empty') }}</p>
                        @endforelse
                    </div>

                    <div class="company-contact-form-wrap">
                        <header class="company-contact-form-header">
                            <h3>{{ __('marketing.contacts.add') }}</h3>
                        </header>
                        <form wire:submit="addContact" class="company-contact-form">
                            <div class="company-form-grid">
                                <label class="operations-label">
                                    {{ __('marketing.contacts.full_name') }} <span class="operations-required">*</span>
                                    <input wire:model="contactFullName" required placeholder="Ad Soyad">
                                    @error('contactFullName') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="operations-label">
                                    {{ __('marketing.contacts.title') }}
                                    <input wire:model="contactTitle" placeholder="Ör. Genel Müdür">
                                    @error('contactTitle') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="operations-label">
                                    {{ __('marketing.contacts.phone') }}
                                    <input class="numeric-data" wire:model="contactPhone" placeholder="+90 ...">
                                    @error('contactPhone') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="operations-label">
                                    {{ __('marketing.contacts.email') }}
                                    <input type="email" wire:model="contactEmail" placeholder="ornek@firma.com">
                                    @error('contactEmail') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="operations-label">
                                    {{ __('marketing.contacts.email_consent') }}
                                    <select wire:model="contactEmailConsent">
                                        @foreach (__('marketing.contacts.consent_options') as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('contactEmailConsent') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="operations-label">
                                    {{ __('marketing.contacts.disclosure_date') }}
                                    <input type="date" wire:model="contactDisclosureDate">
                                    @error('contactDisclosureDate') <span class="operations-error">{{ $message }}</span> @enderror
                                </label>
                            </div>
                            <div class="company-form-actions">
                                <button class="operations-button operations-button--primary" type="submit">
                                    {{ __('marketing.contacts.save') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            {{-- BÖLÜM 2: FIRSATLAR --}}
            <section
                x-data="{
                    open: localStorage.getItem('crm_sec_company_opportunities_{{ $company->id }}') !== 'false',
                    toggle() {
                        this.open = !this.open;
                        localStorage.setItem('crm_sec_company_opportunities_{{ $company->id }}', this.open);
                    }
                }"
                class="company-section"
                :class="{ 'is-collapsed': !open, 'company-section--empty': {{ $leadsCount === 0 ? 'true' : 'false' }} }"
                data-section="opportunities"
                data-testid="company-opportunities-section"
            >
                <header class="company-section__header">
                    <button
                        type="button"
                        @click="toggle"
                        :aria-expanded="open.toString()"
                        class="company-section__trigger"
                        aria-controls="company-section-opportunities-body"
                    >
                        <svg class="company-section__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="company-section__title">{{ __('marketing.company.tabs.opportunities') }}</span>
                        <span class="company-section__count {{ $leadsCount === 0 ? 'company-section__count--zero' : '' }} numeric-data">{{ $leadsCount }}</span>
                    </button>
                </header>

                <div x-show="open" id="company-section-opportunities-body" class="company-section__body company-section__body--flush">
                    <div class="checklist-table-wrap">
                        <table class="checklist-table">
                            <thead>
                                <tr>
                                    <th>{{ __('marketing.detail.program') }}</th>
                                    <th>{{ __('marketing.detail.status') }}</th>
                                    <th>{{ __('marketing.detail.owner') }}</th>
                                    <th>{{ __('marketing.contacts.person') }}</th>
                                    <th>{{ __('marketing.board.last_interaction') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($company->leads as $lead)
                                    <tr>
                                        <td>
                                            <a class="operations-link" href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}">
                                                {{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}
                                            </a>
                                        </td>
                                        <td>{!! \App\Filament\Support\StatusBadge::make($lead->status->color, $lead->status->label) !!}</td>
                                        <td>{{ $lead->owner->name }}</td>
                                        <td>{{ $lead->primaryContact?->full_name ?? __('marketing.consent.not_recorded') }}</td>
                                        <td class="numeric-data">{{ $lead->updated_at->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="operations-placeholder">{{ __('marketing.company.no_opportunities') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- BÖLÜM 3: PROJELER --}}
            <section
                x-data="{
                    open: localStorage.getItem('crm_sec_company_projects_{{ $company->id }}') !== 'false',
                    toggle() {
                        this.open = !this.open;
                        localStorage.setItem('crm_sec_company_projects_{{ $company->id }}', this.open);
                    }
                }"
                class="company-section"
                :class="{ 'is-collapsed': !open, 'company-section--empty': {{ $dealsCount === 0 ? 'true' : 'false' }} }"
                data-section="projects"
                data-testid="company-projects-section"
            >
                <header class="company-section__header">
                    <button
                        type="button"
                        @click="toggle"
                        :aria-expanded="open.toString()"
                        class="company-section__trigger"
                        aria-controls="company-section-projects-body"
                    >
                        <svg class="company-section__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="company-section__title">{{ __('marketing.company.tabs.projects') }}</span>
                        <span class="company-section__count {{ $dealsCount === 0 ? 'company-section__count--zero' : '' }} numeric-data">{{ $dealsCount }}</span>
                    </button>
                </header>

                <div x-show="open" id="company-section-projects-body" class="company-section__body company-section__body--flush">
                    <div class="checklist-table-wrap">
                        <table class="checklist-table">
                            <thead>
                                <tr>
                                    <th>{{ __('operations.detail.fields.reference') }}</th>
                                    <th>{{ __('marketing.detail.program') }}</th>
                                    <th>{{ __('marketing.detail.status') }}</th>
                                    <th>{{ __('operations.detail.fields.manager') }}</th>
                                    <th>{{ __('marketing.company.opened_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($company->deals as $deal)
                                    <tr>
                                        <td>
                                            <a class="operations-link numeric-data" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $deal->id]) }}">
                                                {{ $deal->reference_no }}
                                            </a>
                                        </td>
                                        <td>{{ $deal->programVersion->program->name }}</td>
                                        <td>{!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}</td>
                                        <td>{{ $deal->projectManager?->name ?? __('operations.board.unassigned') }}</td>
                                        <td class="numeric-data">{{ $deal->created_at->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="operations-placeholder">{{ __('marketing.company.no_projects') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- BÖLÜM 4: GÖREVLER --}}
            <section
                x-data="{
                    open: localStorage.getItem('crm_sec_company_tasks_{{ $company->id }}') !== 'false',
                    toggle() {
                        this.open = !this.open;
                        localStorage.setItem('crm_sec_company_tasks_{{ $company->id }}', this.open);
                    }
                }"
                class="company-section"
                :class="{ 'is-collapsed': !open, 'company-section--empty': {{ $tasksCount === 0 ? 'true' : 'false' }} }"
                data-section="tasks"
                data-testid="company-tasks-section"
            >
                <header class="company-section__header">
                    <button
                        type="button"
                        @click="toggle"
                        :aria-expanded="open.toString()"
                        class="company-section__trigger"
                        aria-controls="company-section-tasks-body"
                    >
                        <svg class="company-section__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="company-section__title">{{ __('marketing.company.tabs.tasks') }}</span>
                        <span class="company-section__count {{ $tasksCount === 0 ? 'company-section__count--zero' : '' }} numeric-data">{{ $tasksCount }}</span>
                    </button>
                </header>

                <div x-show="open" id="company-section-tasks-body" class="company-section__body">
                    <livewire:subject-tasks subject-type="company" :subject-id="$company->id" :key="'company-tasks-'.$company->id" />
                </div>
            </section>

            {{-- BÖLÜM 5: ETKİNLİK (Jira-style Activity) --}}
            <section
                x-data="{
                    open: localStorage.getItem('crm_sec_company_activity_{{ $company->id }}') !== 'false',
                    toggle() {
                        this.open = !this.open;
                        localStorage.setItem('crm_sec_company_activity_{{ $company->id }}', this.open);
                    }
                }"
                class="company-section"
                :class="{ 'is-collapsed': !open }"
                data-section="activity"
                data-testid="company-activity-section"
            >
                <header class="company-activity-header">
                    <button
                        type="button"
                        @click="toggle"
                        :aria-expanded="open.toString()"
                        class="company-section__trigger"
                        aria-controls="company-section-activity-body"
                        style="width: auto;"
                    >
                        <svg class="company-section__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                        <span class="company-section__title">{{ __('marketing.company.activity.title') }}</span>
                    </button>

                    <div class="company-activity-toolbar">
                        <nav class="activity-switcher" aria-label="{{ __('marketing.detail.activity.filters_label') }}">
                            @foreach (['comments', 'history', 'all'] as $filter)
                                <button
                                    type="button"
                                    wire:click="setActivityFilter('{{ $filter }}')"
                                    class="activity-switcher__item {{ $activityFilter === $filter ? 'activity-switcher__item--active' : '' }}"
                                >
                                    {{ __('marketing.company.activity.filters.'.$filter) }}
                                </button>
                            @endforeach
                        </nav>

                        <button
                            type="button"
                            wire:click="toggleActivityDirection"
                            class="activity-sort-btn"
                            title="{{ __('marketing.company.activity.sort.label') }}"
                        >
                            <span>{{ $activityDirection === 'desc' ? '↓' : '↑' }}</span>
                            <span>{{ __('marketing.company.activity.sort.'.($activityDirection === 'desc' ? 'newest' : 'oldest')) }}</span>
                        </button>
                    </div>
                </header>

                <div x-show="open" id="company-section-activity-body" class="company-section__body">
                    <div class="company-activity-stream" wire:key="company-activity-{{ $activityFilter }}-{{ $activityDirection }}">
                        @if ($activityFilter === 'comments')
                            <livewire:collaboration-comments subject-type="company" :subject-id="$company->id" :direction="$activityDirection" :key="'company-comments-'.$company->id.'-'.$activityDirection" />
                        @elseif ($activityFilter === 'history')
                            <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" filter="activity" :embedded="true" :direction="$activityDirection" :key="'company-history-'.$company->id.'-'.$activityDirection" />
                        @else
                            <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" filter="all" :embedded="true" :direction="$activityDirection" :key="'company-all-'.$company->id.'-'.$activityDirection" />
                        @endif
                    </div>
                </div>
            </section>
        </main>

        {{-- SAĞ RAY (RAY / ASIDE) --}}
        <aside class="company-workspace__rail" aria-label="{{ __('marketing.detail.details.title') }}">
            <div class="company-rail">
                {{-- AKSİYONLAR --}}
                <div class="company-rail__actions">
                    @if ($this->getAction('start_customer_flow')?->isVisible())
                        <div class="company-rail__primary-action">
                            {{ $this->getAction('start_customer_flow') }}
                        </div>
                    @endif

                    <div class="company-rail__secondary-actions">
                        @if ($this->getAction('create_opportunity')?->isVisible())
                            {{ $this->getAction('create_opportunity') }}
                        @endif
                        @if ($this->getAction('edit')?->isVisible())
                            {{ $this->getAction('edit') }}
                        @endif
                    </div>
                </div>

                {{-- AYRINTILAR PANELİ --}}
                <section class="company-rail__panel">
                    <header class="company-rail__panel-header">
                        <h2>{{ __('marketing.detail.details.title') }}</h2>
                    </header>

                    <dl class="company-details-list">
                        {{-- 1. Firma unvanı --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.legal_name') }}</dt>
                            <dd>{{ $company->legal_name }}</dd>
                        </div>

                        {{-- 2. Sektör --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.industry') }}</dt>
                            @if ($company->industry)
                                <dd>{{ __('panel.industries.'.$company->industry) ?? $company->industry }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 3. İl --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.city') }}</dt>
                            @if ($company->city)
                                <dd>{{ $company->city }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 4. İlçe --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.district') }}</dt>
                            @if ($company->district)
                                <dd>{{ $company->district }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 5. Vergi no --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.tax_number') }}</dt>
                            @if ($company->tax_number)
                                <dd class="numeric-data">{{ $company->tax_number }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 6. Ölçek --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.size') }}</dt>
                            @if ($company->size)
                                <dd>{{ __('panel.company_directory.sizes.'.$company->size) ?? $company->size }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 7. Personel sayısı --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.employee_count') }}</dt>
                            @if ($company->employee_count)
                                <dd class="numeric-data">{{ $company->employee_count }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 8. Kaynak --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.source') }}</dt>
                            @if ($company->source)
                                <dd>{{ $company->source }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 9. Sorumlu --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.owner') }}</dt>
                            @if ($company->owner)
                                <dd>{{ $company->owner->name }}</dd>
                            @else
                                <dd class="is-empty">{{ __('marketing.board.none') }}</dd>
                            @endif
                        </div>

                        {{-- 10. Aktif mi --}}
                        <div class="company-detail-row">
                            <dt>{{ __('panel.fields.is_active') }}</dt>
                            <dd>
                                @if ($company->is_active)
                                    <span class="status-token" data-status="success">
                                        <span class="status-token__shape">✓</span>
                                        {{ __('panel.fields.active') }}
                                    </span>
                                @else
                                    <span class="status-token" data-status="danger">
                                        <span class="status-token__shape">✕</span>
                                        {{ __('panel.fields.inactive') }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    {{-- Sayaç Bloğu --}}
                    <div class="company-rail__counters">
                        <div class="company-rail__counter-item">
                            <dt>{{ __('marketing.company.open_opportunities') }}</dt>
                            <dd class="numeric-data">{{ $openLeadsCount }}</dd>
                        </div>
                        <div class="company-rail__counter-item">
                            <dt>{{ __('marketing.company.projects') }}</dt>
                            <dd class="numeric-data">{{ $dealsCount }}</dd>
                        </div>
                        <div class="company-rail__counter-item">
                            <dt>{{ __('panel.company_directory.contacts') }}</dt>
                            <dd class="numeric-data">{{ $contactsCount }}</dd>
                        </div>
                    </div>

                    {{-- Zaman Damgaları --}}
                    <div class="company-rail__meta">
                        <div>
                            <span>{{ __('marketing.detail.created_at') }}:</span>
                            <strong class="numeric-data">{{ $company->created_at->format('d.m.Y H:i') }}</strong>
                        </div>
                        <div>
                            <span>{{ __('marketing.detail.updated_at') }}:</span>
                            <strong class="numeric-data">{{ $company->updated_at->format('d.m.Y H:i') }}</strong>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>
</x-filament-panels::page>
