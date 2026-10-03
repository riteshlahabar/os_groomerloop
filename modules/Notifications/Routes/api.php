<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\Api\V1\MailDeliveryModeController;
use Modules\Notifications\Http\Controllers\Api\V1\NotificationLogController;
use Modules\Notifications\Http\Controllers\Api\V1\NotificationRetryController;
use Modules\Notifications\Http\Controllers\Api\V1\TenantMailSettingsController;

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

    /*
     | The business's own SMTP account (`D-033`). Gated on `settings.manage`, not on
     | `messages.send`: this is business configuration, which spec §5 gives to the Owner, and it
     | is the same gate the §7 onboarding endpoints and the rest of Settings already carry.
     |
     | No `entitlement:` gate, consistent with Onboarding and Billing: a business that could not
     | make its own reminders come from its own address because of its plan would be one that
     | cannot finish setting up the product it paid for. Making a custom sending domain a paid
     | feature is a §25 packaging decision, and belongs in `plan_features` if it is ever taken —
     | never hard-coded here (invariant #3).
     */
    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('mail-settings', [TenantMailSettingsController::class, 'show'])
            ->name('mail-settings.show');
        Route::put('mail-settings', [TenantMailSettingsController::class, 'update'])
            ->name('mail-settings.update');
        Route::post('mail-settings/test', [TenantMailSettingsController::class, 'sendTest'])
            ->name('mail-settings.test');
    });
});
