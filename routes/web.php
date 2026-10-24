<?php

use BoraIndicar\Laravel\Http\Controllers\DashboardController;
use BoraIndicar\Laravel\Http\Controllers\EventRetryController;
use BoraIndicar\Laravel\Http\Controllers\PingController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('boraindicar.dashboard.path', 'boraindicar'))
    ->middleware([...config('boraindicar.dashboard.middleware', ['web', 'auth']), 'can:viewBoraIndicar'])
    ->name('boraindicar.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('ping', [PingController::class, 'store'])->name('ping');
        Route::post('events/retry-failed', [EventRetryController::class, 'all'])->name('events.retry-failed');
        Route::post('events/{event}/retry', [EventRetryController::class, 'store'])->name('events.retry');
    });
