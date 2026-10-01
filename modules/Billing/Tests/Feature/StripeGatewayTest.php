<?php

namespace Modules\Billing\Tests\Feature;

use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Services\Gateways\StripeGateway;
use Modules\Billing\Tests\Contract\PaymentGatewayContract;
use Tests\TestCase;

/**
 * Holds StripeGateway to the same contract as the fake (CI guard #6, invariant #5).
 *
 * Unlike FakePaymentGatewayTest, this one makes real calls to Stripe's test-mode API, so it
 * needs a real Stripe secret key — which does not exist in this environment yet (see D-025).
 * It skips rather than fails when one is absent: a missing credential is not the same claim as
 * "the driver is broken," and the guard this test exists to satisfy only checks that the test
 * *exists* and names the driver, not that it was run.
 *
 * KNOWN GAP for whoever configures real credentials: `PaymentGatewayContract::acceptableToken()`
 * is reused by both the "store a card" tests and the "successful charge" test, and
 * `decliningDescription()` by the "declined charge" test — reusing the *same* stored card.
 * That matches the fake, which declines by convention on the description string, but Stripe
 * decides a decline by which test card/PaymentMethod was charged, not by description. A single
 * `acceptableToken()` cannot be both "a card Stripe always approves" and "a card Stripe always
 * declines." Before enabling this test for real, either override `acceptableToken()` with a
 * Stripe test PaymentMethod id that approves AND use a second, decline-specific customer/card
 * for `test_a_declined_charge_is_a_result_and_not_an_exception()`, or widen the shared
 * `PaymentGatewayContract` trait to let a driver supply a separate "stores fine but always
 * declines when charged" token. Not resolved here — this environment has no Stripe account to
 * verify either fix against.
 */
final class StripeGatewayTest extends TestCase
{
    use PaymentGatewayContract;

    protected function setUp(): void
    {
        parent::setUp();

        if ((string) config('services.stripe.secret') === '') {
            $this->markTestSkipped('STRIPE_SECRET_KEY is not configured in this environment.');
        }
    }

    protected function gateway(): PaymentGateway
    {
        return new StripeGateway((string) config('services.stripe.secret'));
    }
}
