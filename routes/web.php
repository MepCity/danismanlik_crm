<?php

declare(strict_types=1);

use App\Http\Controllers\DealDocumentArchiveController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MarketingUnsubscribeController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect('/operasyon')
        : redirect('/operasyon/login');
})->name('root');

Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/robots.txt', function (): Response {
    if (app()->environment('staging') || config('app.env') === 'staging') {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']);
    }

    return response("User-agent: *\nDisallow:\n", 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::get('/documents/{file}/download', DocumentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->whereNumber('file')
    ->name('documents.download');

Route::get('/deals/{deal}/documents/archive', DealDocumentArchiveController::class)
    ->middleware(['auth', 'signed'])
    ->whereNumber('deal')
    ->name('deal-documents.archive');

Route::get('/reports/{report}/export', ReportExportController::class)
    ->middleware('auth')
    ->name('reports.export');

Route::get('/e-posta/abonelikten-cik/{contact}', MarketingUnsubscribeController::class)
    ->middleware('signed')
    ->whereNumber('contact')
    ->name('marketing.unsubscribe');
