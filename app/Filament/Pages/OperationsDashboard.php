<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Reporting\Enums\ReportType;
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
            'today_calls' => TodayCalls::getUrl(['filter' => 'today']),
            'overdue_followups' => TodayCalls::getUrl(['filter' => 'overdue']),
            'new_assignments' => DealBoard::getUrl(['filter' => 'new_assignments']),
            'missing_documents' => Reports::getUrl(['report' => ReportType::MissingDocuments->value]),
            'upcoming_deadlines' => Reports::getUrl(['report' => ReportType::UpcomingDeadlines->value]),
            'customer_response' => DealBoard::getUrl(['filter' => 'customer_response']),
            'unassigned_deals' => Reports::getUrl(['report' => ReportType::PendingAssignments->value]),
            default => Reports::getUrl(),
        };
    }

    public function cardIcon(string $key): string
    {
        return match ($key) {
            'today_calls' => 'heroicon-o-phone',
            'overdue_followups' => 'heroicon-o-exclamation-triangle',
            'new_assignments' => 'heroicon-o-inbox-arrow-down',
            'missing_documents' => 'heroicon-o-document-minus',
            'upcoming_deadlines' => 'heroicon-o-calendar-days',
            'customer_response' => 'heroicon-o-chat-bubble-left-right',
            'unassigned_deals' => 'heroicon-o-user-minus',
            default => 'heroicon-o-chart-bar-square',
        };
    }

    public function cardStateLabel(string $state): string
    {
        return __("reporting.dashboard.states.{$state}");
    }
}
