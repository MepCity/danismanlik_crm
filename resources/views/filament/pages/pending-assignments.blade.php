<x-filament-panels::page>
    <div class="assignment-layout" data-testid="pending-assignments">
        <section class="assignment-list">
            @forelse($deals as $deal)
                @php($wonHistory = $deal->originatingLead?->statusHistory?->first(fn ($history) => $history->status->converts_to_deal))
                @php($interaction = $deal->originatingLead?->interactions?->first())
                <article class="operations-panel assignment-card">
                    <header><div><span class="operations-label numeric-data">{{ $deal->reference_no }}</span><h2>{{ $deal->company->legal_name }}</h2></div>{!! \App\Filament\Support\StatusBadge::make($deal->status->color, $deal->status->label) !!}</header>
                    <dl class="assignment-facts">
                        <div><dt>{{ __('operations.detail.fields.program') }}</dt><dd>{{ $deal->programVersion->program->name }}</dd></div>
                        <div><dt>{{ __('operations.detail.fields.marketer') }}</dt><dd>{{ $deal->originatingLead?->owner?->name ?? $deal->openedBy->name }}</dd></div>
                        <div><dt>{{ __('operations.detail.fields.contacted_person') }}</dt><dd>{{ $deal->originatingLead?->primaryContact?->full_name ?? __('operations.detail.not_recorded') }}</dd></div>
                        <div><dt>{{ __('operations.detail.fields.won_at') }}</dt><dd class="numeric-data">{{ $wonHistory?->entered_at?->format('d.m.Y H:i') ?? __('operations.detail.not_recorded') }}</dd></div>
                    </dl>
                    <div class="assignment-summary"><span class="operations-label">{{ __('operations.detail.fields.sale_summary') }}</span><p>{{ $interaction?->note ?? __('operations.detail.not_recorded') }}</p></div>
                    <div class="assignment-action">
                        <label>{{ __('operations.assignment.manager') }}<select wire:model="projectManagerIds.{{ $deal->id }}"><option value="">{{ __('operations.assignment.choose_manager') }}</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }} · {{ __('operations.assignment.active_workload', ['count' => $manager->active_deals_count]) }}</option>@endforeach</select></label>
                        <button class="operations-button operations-button--primary" type="button" wire:click="assign({{ $deal->id }})">{{ __('operations.assignment.assign') }}</button>
                        <a class="operations-link" href="{{ \App\Filament\Pages\DealDetail::getUrl(['deal' => $deal->id]) }}">{{ __('operations.assignment.inspect') }}</a>
                    </div>
                </article>
            @empty
                <div class="operations-panel operations-placeholder">{{ __('operations.assignment.empty') }}</div>
            @endforelse
        </section>
        <aside class="operations-panel assignment-workload">
            <span class="operations-label">{{ __('operations.assignment.workload') }}</span>
            @foreach($managers as $manager)<div><strong>{{ $manager->name }}</strong><span class="numeric-data">{{ $manager->active_deals_count }}</span></div>@endforeach
        </aside>
    </div>
</x-filament-panels::page>
