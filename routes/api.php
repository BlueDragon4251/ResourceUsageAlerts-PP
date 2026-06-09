<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PelicanPlugins\ResourceUsageAlerts\Http\Controllers\AlertApiController;

Route::prefix('api')->middleware(['auth:api', 'throttle:60,1'])->group(function (): void {
    Route::prefix('alerts')->group(function (): void {
        Route::get('/', [AlertApiController::class, 'index']);
        Route::get('/stats', [AlertApiController::class, 'stats']);
        Route::get('/rules', [AlertApiController::class, 'rules']);
        Route::get('/{id}', [AlertApiController::class, 'show']);
        Route::post('/{id}/acknowledge', [AlertApiController::class, 'acknowledge']);
        Route::post('/{id}/resolve', [AlertApiController::class, 'resolve']);
    });
});