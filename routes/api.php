<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SubscriberNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::prefix('v1')->group(function (): void {
    Route::post('/notifications/send', [NotificationController::class, 'send'])
        ->middleware('throttle:60,1')
        ->name('notifications.send');

    Route::get('/subscribers/{subscriberId}/notifications', [SubscriberNotificationController::class, 'index'])
        ->name('subscribers.notifications.index');
});
