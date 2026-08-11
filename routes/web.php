<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documents/{file}/download', DocumentDownloadController::class)
    ->middleware(['auth', 'signed'])
    ->whereNumber('file')
    ->name('documents.download');
