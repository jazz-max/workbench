<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServletController;
use Illuminate\Support\Facades\Route;

// Public file access (no auth required, images only)
Route::get('/public/files/{userId}/{servlet}/{file}', [FileController::class, 'publicDownload'])
    ->where('file', '.*')
    ->name('files.public');

Route::middleware('auth')->group(function () {
    Route::get('/', [ServletController::class, 'index'])->name('servlets.index');
    Route::get('/servlet/{name}', [ServletController::class, 'show'])->name('servlets.show');

    // File management (Phase 2)
    Route::post('/servlet/{name}/upload', [FileController::class, 'upload'])->name('servlets.upload');
    Route::post('/servlet/{name}/params', [FileController::class, 'saveParams'])->name('servlets.saveParams');
    Route::get('/servlet/{name}/input-files', [FileController::class, 'inputFiles'])->name('servlets.inputFiles');
    Route::get('/servlet/{name}/result-files', [FileController::class, 'resultFiles'])->name('servlets.resultFiles');
    Route::get('/servlet/{name}/download/{file}', [FileController::class, 'download'])->name('servlets.download')->where('file', '.*');
    Route::get('/servlet/{name}/download-zip', [FileController::class, 'downloadZip'])->name('servlets.downloadZip');
    Route::delete('/servlet/{name}/clear-in', [FileController::class, 'clearIn'])->name('servlets.clearIn');
    Route::delete('/servlet/{name}/clear-out', [FileController::class, 'clearOut'])->name('servlets.clearOut');

    // Servlet execution (Phase 3)
    Route::post('/servlet/{name}/run', [ServletController::class, 'run'])->name('servlets.run');
    Route::post('/servlet/{name}/stop', [ServletController::class, 'stop'])->name('servlets.stop');
    Route::post('/servlet/{name}/action/{method}', [ServletController::class, 'action'])->name('servlets.action');

    // Опциональная proxy-фича: прозрачный reverse-proxy к сайту-источнику
    // (например, для корзины/чекаута). Включается вместе с методом
    // ServletController::proxy() и фронтенд-блоком в Show.vue. См. README.
    // Route::any('/servlet/{name}/proxy/{path?}', [ServletController::class, 'proxy'])
    //     ->where('path', '.*')
    //     ->name('servlets.proxy');
});

// Breeze направляет сюда после логина/регистрации — отправляем на список сервлетов.
Route::get('/dashboard', fn () => redirect()->route('servlets.index'))
    ->middleware('auth')->name('dashboard');

// Управление аккаунтом (Laravel Breeze).
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
