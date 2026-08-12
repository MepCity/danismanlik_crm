<x-filament-panels::page>
    <div class="dashboard-layout" data-testid="operations-dashboard">
        @if (count($cards) > 0)
            <div class="dashboard-cards">
                @foreach ($cards as $card)
                    <a class="dashboard-card dashboard-card--{{ $card['state'] }}"
                       href="{{ $this->cardUrl($card['key']) }}"
                       data-testid="dashboard-card-{{ $card['key'] }}"
                       data-state="{{ $card['state'] }}"
                       aria-label="{{ $card['label'] }}: {{ $card['count'] }}">
                        <span class="dashboard-card__stripe" aria-hidden="true"></span>
                        <span class="dashboard-card__icon" aria-hidden="true">
                            <x-filament::icon :icon="$this->cardIcon($card['key'])" />
                        </span>
                        <strong class="dashboard-card__value numeric-data">{{ $card['count'] }}</strong>
                        <span class="dashboard-card__label">{{ $card['label'] }}</span>
                        <span class="dashboard-card__status">
                            <span class="dashboard-card__status-mark" aria-hidden="true"></span>
                            {{ $this->cardStateLabel($card['state']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="report-empty">{{ __('reporting.dashboard.no_business_data') }}</div>
        @endif

        <section class="operations-panel dashboard-activities" aria-labelledby="recent-activities-title">
            <header class="report-section-header">
                <h2 id="recent-activities-title">{{ __('reporting.dashboard.recent_activities') }}</h2>
            </header>
            @forelse ($activities as $activity)
                <div class="dashboard-activity">
                    <time class="numeric-data">{{ $activity['occurred_at'] }}</time>
                    <strong>{{ $activity['actor'] }}</strong>
                    <span>{{ $activity['sentence'] }}</span>
                </div>
            @empty
                <div class="report-empty">{{ __('reporting.dashboard.no_activities') }}</div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
