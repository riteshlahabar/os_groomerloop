<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\V1\InvoiceController;
use Modules\Billing\Http\Controllers\Api\V1\PaymentMethodController;
use Modules\Billing\Http\Controllers\Api\V1\SubscriptionCancellationController;
use Modules\Billing\Http\Controllers\Api\V1\SubscriptionController;
use Modules\Billing\Http\Controllers\Api\V1\SubscriptionPlanController;
use Modules\Billing\Http\Controllers\Api\V1\SubscriptionReactivationController;

/*
|--------------------------------------------------------------------------
| Billing API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| Note there is no `entitlement:` middleware anywhere in this file, deliberately. Billing is
| how a business changes its plan — gating it on the plan it currently holds would mean a
| cancelled or downgraded business could not reach the screen that lets it come back.
|
*/

Route::middleware(['auth:sanctum', 'tenant'])->prefix('billing')->name('billing.')->group(function (): void {

    // Reading the bill. Managers and owners can see it; nobody else has billing.view.
    Route::middleware('permission:billing.view')->group(function (): void {
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    });

    // Spending money. Owner only, by way of billing.manage.
    Route::middleware('permission:billing.manage')->group(function (): void {
        Route::post('subscription', [SubscriptionController::class, 'store'])->name('subscription.store');
        Route::put('subscription/plan', SubscriptionPlanController::class)->name('subscription.plan');
        Route::delete('subscription', SubscriptionCancellationController::class)->name('subscription.cancel');
        Route::post('subscription/reactivate', SubscriptionReactivationController::class)->name('subscription.reactivate');

        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
            ->name('payment-methods.destroy');
    });
});
