<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Services\ExcelReportExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        string $report,
        OperationPermissionChecker $permissions,
        ExcelReportExporter $exporter,
    ): BinaryFileResponse {
        $user = $request->user();
        abort_unless($user !== null && $permissions->allows($user, 'report.export'), 403);
        $type = ReportType::tryFrom($report);
        abort_unless($type !== null, 404);

        $export = $exporter->export($type, $user);

        return response()->download($export['path'], $export['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
