<x-filament-panels::page>
    @php($company = $this->record->load([
        'owner',
        'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('full_name'),
    ]))

    @php($primaryContact = $company->contacts->firstWhere('is_primary', true) ?? $company->contacts->first())
    {{-- Every tab and counter reads from this single scoped set. --}}
    @php($summary = $this->workspaceSummary())
    @php($view = \App\Filament\Support\CollaborationView::class)

    <div class="customer-detail" data-testid="company-workspace">

        {{-- 1 · ÜST MÜŞTERİ KİMLİK KARTI --}}
        <section class="customer-identity" data-testid="customer-identity">
            <header class="customer-identity__head">
                <div class="customer-identity__who">
                    <h2 class="customer-identity__title">{{ $company->legal_name }}</h2>
                </div>

                <div class="customer-identity__actions">
                    @if ($this->getAction('create_task')?->isVisible())
                        {{ $this->getAction('create_task') }}
                    @endif

                    <div
                        class="customer-menu"
                        x-data="{
                            open: false,
                            toggle() {
                                this.open = ! this.open;
                                if (! this.open) return;
                                this.$nextTick(() => this.$refs.menu.querySelector('button, [href], [tabindex]:not([tabindex=\'-1\'])')?.focus());
                            },
                            close(refocus = false) {
                                if (! this.open) return;
                                this.open = false;
                                if (refocus) this.$refs.trigger.focus();
                            },
                        }"
                        x-on:keydown.escape.window="close(true)"
                        x-on:click.outside="close()"
                    >
                        <button
                            type="button"
                            x-ref="trigger"
                            class="customer-menu__trigger"
                            aria-haspopup="menu"
                            aria-controls="customer-actions-menu"
                            x-bind:aria-expanded="open.toString()"
                            x-on:click="toggle()"
                            data-testid="customer-actions-trigger"
                        >
                            <span>{{ __('marketing.company.identity.actions') }}</span>
                            <x-filament::icon icon="heroicon-o-chevron-down" aria-hidden="true" />
                        </button>

                        <div
                            id="customer-actions-menu"
                            x-ref="menu"
                            role="menu"
                            aria-label="{{ __('marketing.company.identity.actions_menu') }}"
                            class="customer-menu__list"
                            x-show="open"
                            x-cloak
                            data-testid="customer-actions-menu"
                        >
                            @foreach (['edit', 'start_customer_flow', 'schedule_call'] as $actionName)
                                @if ($this->getAction($actionName)?->isVisible())
                                    <div class="customer-menu__item" role="menuitem" x-on:click="close()">
                                        {{ $this->getAction($actionName) }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                </div>
            </header>

            <ul class="customer-identity__channels">
                <li class="customer-channel">
                    <x-filament::icon icon="heroicon-o-user" aria-hidden="true" />
                    <span class="customer-channel__body">
                        <span class="customer-channel__label">{{ __('marketing.company.identity.primary_contact') }}</span>
                        <span class="customer-channel__value">{{ $primaryContact?->full_name ?? __('marketing.company.identity.no_contact') }}</span>
                    </span>
                </li>
                <li class="customer-channel">
                    <x-filament::icon icon="heroicon-o-briefcase" aria-hidden="true" />
                    <span class="customer-channel__body">
                        <span class="customer-channel__label">{{ __('marketing.company.identity.role') }}</span>
                        <span class="customer-channel__value">{{ $primaryContact?->title ?: __('marketing.company.identity.not_set') }}</span>
                    </span>
                </li>
                <li class="customer-channel">
                    <x-filament::icon icon="heroicon-o-phone" aria-hidden="true" />
                    <span class="customer-channel__body">
                        <span class="customer-channel__label">{{ __('marketing.company.identity.phone') }}</span>
                        @if ($primaryContact?->phone)
                            <a class="customer-channel__value numeric-data" href="tel:{{ $primaryContact->phone }}">{{ $primaryContact->phone }}</a>
                        @else
                            <span class="customer-channel__value operations-muted">{{ __('marketing.calls.no_phone') }}</span>
                        @endif
                    </span>
                </li>
                <li class="customer-channel">
                    <x-filament::icon icon="heroicon-o-envelope" aria-hidden="true" />
                    <span class="customer-channel__body">
                        <span class="customer-channel__label">{{ __('marketing.company.identity.email') }}</span>
                        @if ($primaryContact?->email)
                            <a class="customer-channel__value" href="mailto:{{ $primaryContact->email }}">{{ $primaryContact->email }}</a>
                        @else
                            <span class="customer-channel__value operations-muted">{{ __('marketing.company.identity.not_set') }}</span>
                        @endif
                    </span>
                </li>
            </ul>

            <div class="customer-identity__footer">
                <button
                    type="button"
                    class="customer-identity__toggle"
                    wire:click="toggleDetails"
                    aria-controls="customer-details-region"
                    aria-expanded="{{ $showDetails ? 'true' : 'false' }}"
                    data-testid="customer-details-toggle"
                >
                    <span>{{ $showDetails ? __('marketing.company.identity.hide_details') : __('marketing.company.identity.show_details') }}</span>
                    <x-filament::icon :icon="$showDetails ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'" aria-hidden="true" />
                </button>
            </div>

            @if ($showDetails)
                <div id="customer-details-region" class="customer-details" aria-label="{{ __('marketing.company.identity.details_region') }}" data-testid="customer-details-region">
                    <dl class="customer-details__facts">
                        <div><dt>{{ __('panel.fields.tax_number') }}</dt><dd class="numeric-data">{{ $company->tax_number ?: __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.tax_office') }}</dt><dd>{{ $company->tax_office ?: __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.nace_code') }}</dt><dd class="numeric-data">{{ $company->nace_code ?: __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.size') }}</dt><dd>{{ $company->size ? __('panel.company_directory.sizes.'.$company->size) : __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.employee_count') }}</dt><dd class="numeric-data">{{ $company->employee_count ?: __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.owner') }}</dt><dd>{{ $company->owner?->name ?? __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.city') }}</dt><dd>{{ $company->city ?: __('marketing.company.identity.not_set') }}</dd></div>
                        <div><dt>{{ __('panel.fields.industry') }}</dt><dd>{{ $company->industry ? __('panel.industries.'.$company->industry) : __('marketing.company.identity.not_set') }}</dd></div>
                    </dl>

                    <div class="customer-details__contacts" data-testid="company-contact-cards">
                        @forelse ($company->contacts as $contact)
                            <article class="customer-contact">
                                <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($contact->full_name) }}</span>
                                <div class="customer-contact__body">
                                    <div class="customer-contact__name">
                                        <strong>{{ $contact->full_name }}</strong>
                                        @if ($contact->is_primary)
                                            <span class="contact-primary-badge">{{ __('panel.fields.primary') }}</span>
                                        @endif
                                    </div>
                                    <span class="operations-muted">{{ $contact->title ?: __('marketing.consent.not_recorded') }}</span>
                                </div>
                                <div class="customer-contact__channels">
                                    @if ($contact->phone)<a class="operations-link numeric-data" href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a>@endif
                                    @if ($contact->email)<a class="operations-link" href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@endif
                                </div>
                            </article>
                        @empty
                            <p class="operations-placeholder">{{ __('marketing.detail.contacts.empty') }}</p>
                        @endforelse
                    </div>

                    @can('create', \App\Domain\Crm\Models\Contact::class)
                        <details class="customer-contact-form" data-testid="company-contact-form">
                            <summary>{{ __('marketing.contacts.add') }}</summary>
                            <form wire:submit="addContact" class="operations-inline-form">
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.full_name') }}</span>
                                    <input type="text" wire:model="contactFullName" maxlength="255" required />
                                    @error('contactFullName')<span class="operations-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.title') }}</span>
                                    <input type="text" wire:model="contactTitle" maxlength="255" />
                                </label>
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.phone') }}</span>
                                    <input type="text" wire:model="contactPhone" maxlength="40" />
                                </label>
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.email') }}</span>
                                    <input type="email" wire:model="contactEmail" maxlength="255" />
                                    @error('contactEmail')<span class="operations-field-error">{{ $message }}</span>@enderror
                                </label>
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.email_consent') }}</span>
                                    <select wire:model="contactEmailConsent">
                                        @foreach (['unknown', 'granted', 'denied'] as $consent)
                                            <option value="{{ $consent }}">{{ __('marketing.contacts.consent_options.'.$consent) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="intake-field">
                                    <span>{{ __('marketing.contacts.disclosure_date') }}</span>
                                    <input type="date" wire:model="contactDisclosureDate" />
                                </label>
                                <div class="operations-actions">
                                    <button class="operations-button operations-button--primary">{{ __('marketing.contacts.save') }}</button>
                                </div>
                            </form>
                        </details>
                    @endcan
                </div>
            @endif
        </section>

        {{-- 2 · HIZLI NOT --}}
        <div class="customer-note" data-testid="customer-note">
            <livewire:collaboration-comments
                subject-type="company"
                :subject-id="$company->id"
                direction="desc"
                :compact="true"
                :key="'company-note-'.$company->id"
            />
        </div>

        {{-- 3 · SEKMELER --}}
        <nav
            class="customer-tabs"
            role="tablist"
            aria-label="{{ __('marketing.company.workspace.tabs_label') }}"
            data-testid="customer-tabs"
            x-data="{
                move(step) {
                    const tabs = [...$el.querySelectorAll('[role=\'tab\']')];
                    const current = tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
                    const next = tabs[(current + step + tabs.length) % tabs.length];
                    next?.focus();
                    next?.click();
                },
            }"
            x-on:keydown.arrow-right.prevent="move(1)"
            x-on:keydown.arrow-left.prevent="move(-1)"
        >
            @foreach ([
                'activities' => 'heroicon-o-bolt',
                'tasks' => 'heroicon-o-check-circle',
                'opportunities' => 'heroicon-o-flag',
                'files' => 'heroicon-o-folder-open',
            ] as $tab => $icon)
                <button
                    type="button"
                    role="tab"
                    id="customer-tab-{{ $tab }}"
                    aria-controls="customer-panel-{{ $tab }}"
                    aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                    tabindex="{{ $activeTab === $tab ? '0' : '-1' }}"
                    class="customer-tab {{ $activeTab === $tab ? 'customer-tab--active' : '' }}"
                    wire:click="setActiveTab('{{ $tab }}')"
                    data-testid="customer-tab-{{ $tab }}"
                >
                    <x-filament::icon :icon="$icon" aria-hidden="true" />
                    <span>{{ __('marketing.company.workspace.tabs.'.$tab) }}</span>
                </button>
            @endforeach
        </nav>

        {{-- 4-5 · İKİ KOLON --}}
        <div class="customer-columns">
            <main
                class="customer-columns__main"
                role="tabpanel"
                id="customer-panel-{{ $activeTab }}"
                aria-labelledby="customer-tab-{{ $activeTab }}"
                tabindex="0"
                wire:key="customer-panel-{{ $activeTab }}"
            >
                @if ($activeTab === 'activities')
                    <div class="customer-panel" data-testid="customer-panel-activities">
                        <header class="customer-panel__head">
                            <h3>{{ __('marketing.company.activity.title') }}</h3>
                            <div class="customer-panel__tools">
                                <nav class="activity-switcher" aria-label="{{ __('marketing.detail.activity.filters_label') }}">
                                    @foreach (['comments', 'history', 'all'] as $filter)
                                        <button
                                            type="button"
                                            wire:click="setActivityFilter('{{ $filter }}')"
                                            class="activity-switcher__item {{ $activityFilter === $filter ? 'activity-switcher__item--active' : '' }}"
                                        >{{ __('marketing.company.activity.filters.'.$filter) }}</button>
                                    @endforeach
                                </nav>
                                <button type="button" wire:click="toggleActivityDirection" class="customer-sort" title="{{ __('marketing.company.activity.sort.label') }}">
                                    <span aria-hidden="true">{{ $activityDirection === 'desc' ? '↓' : '↑' }}</span>
                                    <span>{{ __('marketing.company.activity.sort.'.($activityDirection === 'desc' ? 'newest' : 'oldest')) }}</span>
                                </button>
                            </div>
                        </header>

                        <div wire:key="company-activity-{{ $activityFilter }}-{{ $activityDirection }}">
                            @if ($activityFilter === 'comments')
                                <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" filter="comment" :embedded="true" variant="customer" :direction="$activityDirection" :key="'company-notes-'.$company->id.'-'.$activityDirection" />
                            @elseif ($activityFilter === 'history')
                                <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" filter="activity" :embedded="true" variant="customer" :direction="$activityDirection" :key="'company-history-'.$company->id.'-'.$activityDirection" />
                            @else
                                <livewire:collaboration-timeline subject-type="company" :subject-id="$company->id" filter="all" :embedded="true" variant="customer" :direction="$activityDirection" :key="'company-all-'.$company->id.'-'.$activityDirection" />
                            @endif
                        </div>
                    </div>
                @elseif ($activeTab === 'tasks')
                    <div class="customer-panel" data-testid="customer-panel-tasks">
                        <livewire:subject-tasks subject-type="company" :subject-id="$company->id" :key="'company-tasks-'.$company->id" />
                    </div>
                @elseif ($activeTab === 'opportunities')
                    <div class="customer-panel" data-testid="customer-panel-opportunities">
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
                                    @forelse($summary->leads as $lead)
                                        <tr>
                                            <td><a class="operations-link" href="{{ \App\Filament\Pages\LeadDetail::getUrl(['lead' => $lead->id]) }}">{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</a></td>
                                            <td>{!! \App\Filament\Support\StatusBadge::make($lead->status->color, $lead->status->label) !!}</td>
                                            <td>{{ $lead->owner->name }}</td>
                                            <td>{{ $lead->primaryContact?->full_name ?? __('marketing.consent.not_recorded') }}</td>
                                            <td class="numeric-data">{{ $lead->updated_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="operations-placeholder">{{ __('marketing.company.no_opportunities') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="customer-panel" data-testid="customer-panel-files">
                        <div class="checklist-table-wrap">
                            <table class="checklist-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('marketing.company.workspace.files.reference') }}</th>
                                        <th>{{ __('marketing.company.workspace.files.program') }}</th>
                                        <th>{{ __('marketing.company.workspace.files.status') }}</th>
                                        <th>{{ __('marketing.company.workspace.files.manager') }}</th>
                                        <th>{{ __('marketing.company.workspace.files.updated') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($summary->deals as $deal)
                                        <tr>
                                            <td><a class="operations-link numeric-data" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $deal->id]) }}">{{ $deal->reference_no }}</a></td>
                                            <td>{{ $deal->programVersion->program->name }}</td>
                                            <td>{!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}</td>
                                            <td>{{ $deal->projectManager?->name ?? __('marketing.company.workspace.files.unassigned') }}</td>
                                            <td class="numeric-data">{{ $deal->updated_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="operations-placeholder">{{ __('marketing.company.workspace.files.empty') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </main>

            {{-- 6 · SAĞ ÖZET --}}
            <aside class="customer-columns__aside" aria-label="{{ __('marketing.company.workspace.summary_title') }}" data-testid="customer-summary">
                {{-- Primary counter card --}}
                <section class="ops-hero" data-testid="ops-hero">
                    <div class="ops-hero__body">
                        <span class="ops-hero__label">{{ __('marketing.company.workspace.summary.active_deals') }}</span>
                        <span class="ops-hero__value numeric-data">{{ $summary->activeDeals }}</span>
                    </div>
                    <button
                        type="button"
                        class="ops-hero__action"
                        wire:click="setActiveTab('files')"
                        data-testid="ops-hero-action"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-right" aria-hidden="true" />
                        <span>{{ __('marketing.company.workspace.tabs.files') }}</span>
                    </button>
                </section>

                @php($sections = [
                    ['key' => 'pending_documents', 'rows' => $summary->pendingDocumentDeals(), 'kind' => 'deal', 'tone' => 'waiting'],
                    ['key' => 'open_leads', 'rows' => $summary->openLeadRows(), 'kind' => 'lead', 'tone' => 'info'],
                    ['key' => 'overdue_tasks', 'rows' => $summary->overdueTaskRows(), 'kind' => 'task', 'tone' => 'danger'],
                    ['key' => 'open_tasks', 'rows' => $summary->openTaskRows(), 'kind' => 'task', 'tone' => 'waiting'],
                    ['key' => 'completed_tasks', 'rows' => $summary->completedTaskRows(), 'kind' => 'task', 'tone' => 'success'],
                ])

                @foreach ($sections as $section)
                    <section class="ops-section" data-testid="ops-section-{{ $section['key'] }}">
                        <h3 class="ops-section__title">
                            {{ __('marketing.company.workspace.sections.'.$section['key']) }}
                            <span class="ops-section__count numeric-data">({{ $section['rows']->count() }})</span>
                        </h3>

                        @foreach ($section['rows'] as $row)
                            <article class="ops-card" data-testid="ops-card">
                                @if ($section['kind'] === 'deal')
                                    <h4 class="ops-card__title numeric-data">{{ $row->reference_no }}</h4>
                                    <p class="ops-card__context">{{ $row->programVersion->program->name }}</p>
                                    <footer class="ops-card__foot">
                                        <span class="ops-badge ops-badge--{{ $section['tone'] }}">
                                            {{ __('marketing.company.workspace.summary.pending_documents') }}: {{ $row->pending_documents_count }}
                                        </span>
                                        <time class="numeric-data">{{ $row->updated_at->format('d.m.Y') }}</time>
                                    </footer>
                                @elseif ($section['kind'] === 'lead')
                                    <h4 class="ops-card__title">{{ $row->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</h4>
                                    <p class="ops-card__context">{{ $row->owner->name }}</p>
                                    <footer class="ops-card__foot">
                                        {!! \App\Filament\Support\StatusBadge::make($row->status->color, $row->status->label) !!}
                                        <time class="numeric-data">{{ $row->updated_at->format('d.m.Y') }}</time>
                                    </footer>
                                @else
                                    <h4 class="ops-card__title">{{ $row->title }}</h4>
                                    <p class="ops-card__context">{{ $row->assignee?->name ?? __('marketing.company.workspace.files.unassigned') }}</p>
                                    <footer class="ops-card__foot">
                                        <span class="ops-badge ops-badge--{{ $section['tone'] }}">
                                            <x-filament::icon
                                                :icon="$section['key'] === 'completed_tasks' ? 'heroicon-o-check-circle' : ($section['key'] === 'overdue_tasks' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')"
                                                aria-hidden="true"
                                            />
                                            {{ __('marketing.company.workspace.sections.'.$section['key']) }}
                                        </span>
                                        <time class="numeric-data">{{ ($row->due_at ?? $row->updated_at)->format('d.m.Y') }}</time>
                                    </footer>
                                @endif
                            </article>
                        @endforeach
                    </section>
                @endforeach

                <section class="ops-section" data-testid="ops-section-owner">
                    <h3 class="ops-section__title">{{ __('marketing.company.workspace.summary.owner') }}</h3>
                    <article class="ops-card">
                        <h4 class="ops-card__title">{{ $summary->ownerName ?? __('marketing.company.workspace.summary.none') }}</h4>
                        <footer class="ops-card__foot">
                            <span>{{ __('marketing.company.workspace.summary.last_activity') }}</span>
                            <time class="numeric-data">{{ $summary->lastActivityAt?->format('d.m.Y H:i') ?? __('marketing.company.workspace.summary.never') }}</time>
                        </footer>
                    </article>
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
