<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Reporting\Services\DashboardQuery;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Auth;

final class OperationsDashboard extends Dashboard
{
    protected string $view = 'filament.pages.operations-dashboard';

    public static function getNavigationLabel(): string
    {
        return __('reporting.dashboard.navigation');
    }

    public function getTitle(): string
    {
        return __('reporting.dashboard.title');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $dashboard = app(DashboardQuery::class);

        return [
            'cards' => $dashboard->cards($user),
            'activities' => $dashboard->recentActivities($user),
        ];
    }

    public function cardUrl(string $key): string
    {
        return match ($key) {
            'today_calls', 'overdue_followups' => TodayCalls::getUrl(),
            'new_assignments', 'missing_documents', 'upcoming_deadlines', 'customer_response', 'unassigned_deals' => Reports::getUrl(),
            default => Reports::getUrl(),
        };
    }
}
