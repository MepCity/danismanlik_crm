<x-filament-panels::page>
    <div class="lead-detail lead-detail--workspace" data-testid="lead-detail">
        <div class="lead-workspace">
            <main class="lead-workspace__main">
                <section class="lead-section" aria-labelledby="lead-overview-title">
                    <header class="lead-section__header">
                        <div>
                            <span class="operations-label">{{ __('marketing.detail.overview.eyebrow') }}</span>
                            <h2 id="lead-overview-title">{{ __('marketing.detail.overview.title') }}</h2>
                        </div>
                    </header>
                    <div class="lead-overview">
                        <div class="lead-overview__identity">
                            <span class="lead-overview__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($lead->company->legal_name, 0, 1)) }}</span>
                            <div>
                                <strong>{{ $lead->company->legal_name }}</strong>
                                <span>{{ $lead->company->city }}@if($lead->company->district) · {{ $lead->company->district }}@endif</span>
                            </div>
                        </div>
                        <dl class="lead-overview__facts">
                            <div><dt>{{ __('marketing.detail.program') }}</dt><dd>{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</dd></div>
                            <div><dt>{{ __('marketing.detail.primary_contact') }}</dt><dd>{{ $lead->primaryContact?->full_name ?? __('marketing.consent.not_recorded') }}@if($lead->primaryContact?->title)<span>{{ $lead->primaryContact->title }}</span>@endif</dd></div>
                        </dl>
                    </div>
                </section>

                <section class="lead-section" aria-labelledby="lead-contacts-title">
                    <header class="lead-section__header">
                        <div>
                            <span class="operations-label">{{ __('marketing.detail.contacts.eyebrow') }}</span>
                            <h2 id="lead-contacts-title">{{ __('marketing.detail.contacts.title') }}</h2>
                        </div>
                        <span class="lead-section__count numeric-data">{{ $lead->company->contacts->count() }}</span>
                    </header>
                    <div class="lead-contact-list">
                        @forelse ($lead->company->contacts as $contact)
                            <article class="lead-contact">
                                <span class="comment-avatar" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($contact->full_name) }}</span>
                                <div class="lead-contact__identity">
                                    <strong>{{ $contact->full_name }}</strong>
                                    <span>{{ $contact->title ?: __('marketing.consent.not_recorded') }}</span>
                                </div>
                                <div class="lead-contact__channels">
                                    @if($contact->phone)<a class="operations-link numeric-data" href="tel:{{ $contact->phone }}">{{ $contact->phone }}</a>@else<span>{{ __('marketing.calls.no_phone') }}</span>@endif
                                    @if($contact->email)<a class="operations-link" href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@endif
                                </div>
                                <div class="lead-contact__actions">
                                    @if($contact->phone)<a class="operations-button operations-button--primary numeric-data" href="tel:{{ $contact->phone }}">{{ __('marketing.calls.call') }}</a>@endif
                                </div>
                            </article>
                        @empty
                            <p class="operations-placeholder">{{ __('marketing.detail.contacts.empty') }}</p>
                        @endforelse
                    </div>
                </section>

                @include('filament.pages.partials.interactions', ['interactions' => $lead->interactions, 'contacts' => $lead->company->contacts->where('is_active', true)])
            </main>

            <aside class="lead-workspace__aside" aria-labelledby="lead-details-title">
                <section class="lead-aside-card">
                    <header class="lead-aside-card__header">
                        <h2 id="lead-details-title">{{ __('marketing.detail.details.title') }}</h2>
                    </header>
                    <dl class="lead-details-list">
                        <div><dt>{{ __('marketing.detail.status') }}</dt><dd>{!! \App\Filament\Support\StatusBadge::make($lead->status->color, $lead->status->label) !!}</dd></div>
                        <div><dt>{{ __('marketing.detail.owner') }}</dt><dd><span class="lead-person"><span class="comment-avatar comment-avatar--small" aria-hidden="true">{{ \App\Filament\Support\CollaborationView::initials($lead->owner->name) }}</span>{{ $lead->owner->name }}</span></dd></div>
                        <div><dt>{{ __('marketing.detail.program') }}</dt><dd>{{ $lead->interestedProgramVersion?->program?->name ?? __('marketing.board.no_program') }}</dd></div>
                        <div><dt>{{ __('marketing.detail.next_call') }}</dt><dd class="numeric-data">{{ $lead->next_call_at?->format('d.m.Y H:i') ?? __('marketing.detail.not_planned') }}</dd></div>
                        <div><dt>{{ __('marketing.detail.company') }}</dt><dd>{{ $lead->company->legal_name }}</dd></div>
                        <div><dt>{{ __('marketing.detail.city') }}</dt><dd>{{ $lead->company->city }}</dd></div>
                        <div><dt>{{ __('marketing.detail.created_at') }}</dt><dd class="numeric-data">{{ $lead->created_at->format('d.m.Y H:i') }}</dd></div>
                        <div><dt>{{ __('marketing.detail.updated_at') }}</dt><dd class="numeric-data">{{ $lead->updated_at->format('d.m.Y H:i') }}</dd></div>
                        @if ($lead->convertedDeal)
                            <div><dt>{{ __('marketing.detail.converted_deal') }}</dt><dd><a class="operations-link numeric-data" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $lead->convertedDeal->id]) }}">{{ $lead->convertedDeal->reference_no }}</a></dd></div>
                        @endif
                    </dl>
                </section>
            </aside>
        </div>

        <section class="lead-activity" aria-labelledby="lead-activity-title" data-testid="lead-activity">
            <header class="lead-activity__header">
                <div>
                    <span class="operations-label">{{ __('marketing.detail.activity.eyebrow') }}</span>
                    <h2 id="lead-activity-title">{{ __('marketing.detail.activity.title') }}</h2>
                </div>
                <nav class="activity-switcher" aria-label="{{ __('marketing.detail.activity.filters_label') }}">
                    @foreach (['comments', 'history', 'all'] as $filter)
                        <button type="button" wire:click="setActivityFilter('{{ $filter }}')" class="activity-switcher__item {{ $activityFilter === $filter ? 'activity-switcher__item--active' : '' }}">
                            {{ __('marketing.detail.activity.filters.'.$filter) }}
                        </button>
                    @endforeach
                </nav>
            </header>

            <div class="lead-activity__body">
                @if ($activityFilter === 'comments')
                    <livewire:collaboration-comments subject-type="lead" :subject-id="$lead->id" :key="'lead-comments-'.$lead->id" />
                @elseif ($activityFilter === 'history')
                    <livewire:collaboration-timeline subject-type="lead" :subject-id="$lead->id" filter="activity" :embedded="true" :key="'lead-history-'.$lead->id" />
                @else
                    <livewire:collaboration-timeline subject-type="lead" :subject-id="$lead->id" filter="all" :embedded="true" :key="'lead-all-'.$lead->id" />
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
