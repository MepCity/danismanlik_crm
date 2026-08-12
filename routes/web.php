<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documents/{file}/download', DocumentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->whereNumber('file')
    ->name('documents.download');

Route::get('/reports/{report}/export', ReportExportController::class)
    ->middleware('auth')
    ->name('reports.export');
