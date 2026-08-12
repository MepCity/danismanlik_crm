<x-filament-panels::page>
    <div class="dashboard-layout" data-testid="operations-dashboard">
        @if (count($cards) > 0)
            <div class="dashboard-cards">
                @foreach ($cards as $card)
                    <a class="dashboard-card dashboard-card--{{ $card['state'] }}" href="{{ $this->cardUrl($card['key']) }}">
                        <span class="dashboard-card__shape" aria-hidden="true">{{ $card['state'] === 'danger' ? '!' : ($card['state'] === 'waiting' ? '◷' : '•') }}</span>
                        <span class="dashboard-card__label">{{ $card['label'] }}</span>
                        <strong class="dashboard-card__value numeric-data">{{ $card['count'] }}</strong>
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
