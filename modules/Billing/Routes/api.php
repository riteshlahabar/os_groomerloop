<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\V1\InvoiceController;
use Modules\Billing\Http\Controllers\Api\V1\PaymentCapabilityController;
use Modules\Billing\Http\Controllers\Api\V1\PaymentMethodController;
use Modules\Billing\Http\Controllers\Api\V1\StripeWebhookController;
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

/*
| Gateway webhooks (D-050) sit outside the authenticated group below, and outside `tenant`:
| Stripe holds no session, and the event itself is what says which tenant it concerns. The
| signature is the authentication — see StripeWebhookController and StripeWebhookTranslator.
|
| Its own rate limiter, not the shared `api` one (120/min per IP): a gateway replaying a
| backlog after an outage would blow that budget, and a 429 to Stripe delays settlement
| silently. Same reasoning as `public-availability` on 2026-10-03.
*/
Route::middleware('throttle:gateway-webhooks')
    ->prefix('billing/webhooks')
    ->name('billing.webhooks.')
    ->group(function (): void {
        Route::post('stripe', StripeWebhookController::class)->name('stripe');
    });

Route::middleware(['auth:sanctum', 'tenant'])->prefix('billing')->name('billing.')->group(function (): void {

    // Reading the bill. Managers and owners can see it; nobody else has billing.view.
    Route::middleware('permission:billing.view')->group(function (): void {
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');

        // What the browser may do about cards here (which client integration, which public
        // key) — asked by /admin/billing rather than hard-coded into it, invariant #5. Read
        // alongside the card list, so it sits under the same billing.view gate.
        Route::get('payment-capabilities', PaymentCapabilityController::class)->name('payment-capabilities');
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
