<x-filament-panels::page>
    <div class="reports-layout" data-testid="reports-page">
        <nav class="report-tabs" aria-label="{{ __('reporting.report_selector') }}">
            @foreach ($reportTypes as $reportType)
                @if ($reportType === \App\Domain\Reporting\Enums\ReportType::DealBoard)
                    <a class="report-tab" href="{{ \App\Filament\Pages\DealBoard::getUrl() }}">
                        {{ $reportType->label() }}
                    </a>
                @else
                    <button type="button"
                            class="report-tab {{ $activeReport === $reportType->value ? 'report-tab--active' : '' }}"
                            wire:click="selectReport('{{ $reportType->value }}')">
                        {{ $reportType->label() }}
                    </button>
                @endif
            @endforeach
        </nav>

        <section class="operations-panel">
            <header class="report-section-header">
                <div>
                    <h2>{{ $table->type->label() }}</h2>
                    <p>{{ __('reporting.row_summary', ['shown' => $table->rows->count(), 'total' => $table->total]) }}</p>
                </div>
                @if ($canExport)
                    <a class="operations-button operations-button--primary" href="{{ route('reports.export', ['report' => $table->type->value]) }}">
                        {{ __('reporting.export') }}
                    </a>
                @else
                    <span class="report-export-denied">{{ __('reporting.export_permission_required') }}</span>
                @endif
            </header>

            <div class="report-table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            @foreach ($table->columns as $column)
                                <th class="{{ $column->numeric ? 'numeric-data' : '' }}">{{ $column->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($table->rows as $row)
                            <tr>
                                @foreach ($table->columns as $column)
                                    <td class="{{ $column->numeric ? 'numeric-data' : '' }}">{{ $this->displayValue($column, $row) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($table->columns) }}" class="report-empty">{{ $table->type->emptyMessage() }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
