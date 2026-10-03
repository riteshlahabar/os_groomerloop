<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\Api\V1\MailDeliveryModeController;
use Modules\Notifications\Http\Controllers\Api\V1\NotificationLogController;
use Modules\Notifications\Http\Controllers\Api\V1\NotificationRetryController;

/*
|--------------------------------------------------------------------------
| Notifications API routes (spec §13)
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| No `entitlement:` gate here, deliberately. Transactional messaging is part of taking a booking at
| all — spec §25 grants `online_booking` and `core_os` on every plan, and a business that cannot tell
| a customer their appointment is confirmed has not been sold a cheaper product, it has been sold a
| broken one. The place a plan *does* belong is the SMS channel ("SMS where provider/plan supports
| it", §13), which has to wait for a real SMS driver — `LogSmsProvider` reaches nobody today, so
| gating it now would gate nothing.
|
| Sending is a separate permission from reading (`D-031`): the log is a record, a retry is an outbound
| message to a customer.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::middleware('permission:messages.view')->group(function (): void {
        Route::get('notifications', [NotificationLogController::class, 'index'])->name('notifications.index');

        // Whether anything actually leaves the server for this business. Read by the Messages
        // screen's banner so it stops claiming nothing is connected once SMTP is set up.
        Route::get('notifications/delivery-mode', MailDeliveryModeController::class)
            ->name('notifications.delivery-mode');
    });

    Route::middleware('permission:messages.send')->group(function (): void {
        Route::post('notifications/{notificationLog}/retry', NotificationRetryController::class)
            ->name('notifications.retry');
    });
});
