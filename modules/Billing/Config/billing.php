<?php

/**
 * Spec §24 leaves the gateway, tax treatment and exact windows to be finalised by
 * product/finance (§40). They are configuration here rather than constants in code, so
 * finance can change a grace period without a deploy.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Which driver satisfies the PaymentGateway contract. "fake" is a real driver that
    | passes the same contract test as every other (CI guard #6), and is the correct
    | choice in development and in the test suite. "stripe" (D-025) needs
    | services.stripe.secret configured — BillingServiceProvider refuses to boot with
    | BILLING_GATEWAY=stripe and no key, rather than silently falling back to the fake.
    |
    */
    'gateway' => env('BILLING_GATEWAY', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    |
    | Days a new business may use its chosen plan before the first charge. Zero means
    | no trial — charge immediately.
    |
    */
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Dunning
    |--------------------------------------------------------------------------
    |
    | "Failed-payment handling" and "configurable grace period" (§24).
    |
    | A failed charge moves the subscription to past_due. After `max_attempts` failures
    | it enters a grace period of `grace_days`, during which the business keeps full
    | access — invariant #4, and simple decency: a groomer with a dozen dogs booked in
    | tomorrow must not be locked out because a card expired.
    |
    | When grace expires the subscription cancels, the business drops to the default
    | plan, and every customer, pet and appointment record survives untouched.
    |
    */
    'dunning' => [
        'max_attempts' => (int) env('BILLING_DUNNING_ATTEMPTS', 3),
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoicing
    |--------------------------------------------------------------------------
    |
    | Tax is deliberately not calculated here. US sales tax on SaaS varies by state and
    | is a finance decision (§40); until it is made, tax_cents stays zero and the column
    | exists so adding it later is not a migration against a live invoices table.
    |
    */
    'invoice' => [
        'prefix' => env('BILLING_INVOICE_PREFIX', 'GL'),
        'due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),
    ],
];
