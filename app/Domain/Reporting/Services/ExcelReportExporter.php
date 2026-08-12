<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Domain\Reporting\DTOs\ReportColumn;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Models\ReportExport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

final readonly class ExcelReportExporter
{
    public function __construct(
        private ReportQuery $reports,
        private Filesystem $files,
        private OperationPermissionChecker $permissions,
    ) {}

    /** @return array{path: string, filename: string, row_count: int} */
    public function export(ReportType $type, User $actor): array
    {
        if (! $this->permissions->allows($actor, 'report.export')) {
            throw new AuthorizationException((string) trans('reporting.export_forbidden'));
        }

        $directory = storage_path('app/report-exports');
        $this->files->ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid().'.xlsx';
        $columns = $this->reports->columns($type);
        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::DARK_BLUE);
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(Str::limit($type->label(), 31, ''));
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        foreach ($columns as $index => $column) {
            $sheet->setColumnWidth($this->columnWidth($column), $index + 1);
        }

        $writer->addRow(Row::fromValues(array_map(static fn ($column): string => $column->label, $columns), $headerStyle));

        $rowCount = 0;
        foreach ($this->reports->cursor($type, $actor) as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row->{$column->key} ?? null;
                $values[] = $column->numeric && $value !== null ? (float) $value : $value;
            }
            $writer->addRow(Row::fromValues($values));
            $rowCount++;
        }
        $writer->close();

        ReportExport::query()->create([
            'actor_id' => $actor->id,
            'report_code' => $type->value,
            'row_count' => $rowCount,
        ]);

        return [
            'path' => $path,
            'filename' => $type->value.'-'.now()->format('Ymd-His').'.xlsx',
            'row_count' => $rowCount,
        ];
    }

    private function columnWidth(ReportColumn $column): float
    {
        return match ($column->key) {
            'company_name' => 30,
            'program_name' => 42,
            'document_name' => 34,
            'project_manager', 'status_label', 'document_status' => 24,
            'reference_no' => 18,
            default => 20,
        };
    }
}
