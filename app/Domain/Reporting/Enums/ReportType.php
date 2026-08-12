<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Enums;

enum ReportType: string
{
    case DealBoard = 'deal_board';
    case PendingAssignments = 'pending_assignments';
    case ProjectManagerWorkload = 'pm_workload';
    case MissingDocuments = 'missing_documents';
    case UpcomingDeadlines = 'upcoming_deadlines';
    case ConversionFunnel = 'conversion_funnel';

    public function label(): string
    {
        return __("reporting.reports.{$this->value}.title");
    }

    public function emptyMessage(): string
    {
        return __("reporting.reports.{$this->value}.empty");
    }
}
