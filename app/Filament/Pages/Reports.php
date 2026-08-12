<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Reporting\DTOs\ReportColumn;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Services\ReportQuery;
use App\Support\Authorization\ScopedQuery;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

final class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $slug = 'raporlar';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.reports';

    #[Url(as: 'report')]
    public string $activeReport = 'deal_board';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->can('report.view')
            && app(ScopedQuery::class)->allowsAny($user, 'report.view');
    }

    public static function getNavigationLabel(): string
    {
        return __('reporting.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.reports');
    }

    public function getTitle(): string
    {
        return __('reporting.title');
    }

    public function selectReport(string $report): void
    {
        abort_unless(ReportType::tryFrom($report) !== null, 404);
        $this->activeReport = $report;
    }

    public function displayValue(ReportColumn $column, object $row): string
    {
        $value = $row->{$column->key} ?? null;

        if ($value === null || $value === '') {
            return __('reporting.not_available');
        }

        if ($column->key === 'document_status') {
            $key = 'collaboration.document_statuses.'.$value;

            return trans()->has($key) ? trans($key) : (string) $value;
        }

        if (in_array($column->key, ['due_at', 'application_closes_at'], true)) {
            return Carbon::parse((string) $value)->format('d.m.Y H:i');
        }

        if (str_ends_with($column->key, '_rate')) {
            return number_format((float) $value, 1, ',', '.').' %';
        }

        if ($column->numeric) {
            $decimals = str_ends_with($column->key, '_days') ? 2 : 0;

            return number_format((float) $value, $decimals, ',', '.');
        }

        return (string) $value;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $type = ReportType::tryFrom($this->activeReport);
        abort_unless($type !== null, 404);

        return [
            'reportTypes' => ReportType::cases(),
            'table' => app(ReportQuery::class)->table($type, $user),
            'canExport' => $user->can('report.export'),
        ];
    }
}
