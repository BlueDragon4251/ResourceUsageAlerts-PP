<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PelicanPlugins\ResourceUsageAlerts\Http\Controllers\PushServiceWorkerController;
use PelicanPlugins\ResourceUsageAlerts\Http\Controllers\PushSubscriptionController;

Route::get('/resource-usage-alerts-sw.js', PushServiceWorkerController::class)
    ->name('resourceusagealerts.push.worker');

Route::prefix('/resource-usage-alerts/push')
    ->name('resourceusagealerts.push.')
    ->middleware(['auth', 'auth.session'])
    ->controller(PushSubscriptionController::class)
    ->group(function (): void {
        Route::get('/configuration', 'configuration')->name('configuration');
        Route::post('/subscribe', 'store')->middleware('throttle:10,1')->name('subscribe');
        Route::delete('/subscribe', 'destroy')->middleware('throttle:10,1')->name('unsubscribe');
        Route::post('/test', 'test')->middleware('throttle:10,1')->name('test');
    });
