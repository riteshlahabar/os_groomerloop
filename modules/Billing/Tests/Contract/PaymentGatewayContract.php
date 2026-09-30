<?php

namespace Modules\Billing\Tests\Contract;

use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Exceptions\GatewayFailure;

/**
 * The shared contract test every PaymentGateway driver must pass (CI guard #6).
 *
 * Invariant #5 says a provider swap must not touch business logic. That only holds if every
 * driver behaves the same way at the boundary — and the usual way it stops holding is that
 * the fake used in tests grows conveniences the real driver does not have, so the suite goes
 * green against behaviour production does not exhibit.
 *
 * So the rules live here, once, and each driver's test case extends this and supplies the
 * driver. A new driver is a ten-line test class; if it does not pass, it is not a driver.
 *
 * Deliberately uses only the interface. Any test that needs to reach past PaymentGateway to
 * set a driver up belongs in that driver's own test case, not in here.
 */
trait PaymentGatewayContract
{
    abstract protected function gateway(): PaymentGateway;

    /**
     * A token the driver should accept. Real drivers override with their provider's test
     * token; the fake accepts anything without a magic word in it.
     */
    protected function acceptableToken(): string
    {
        return 'tok_test_visa';
    }

    /**
     * A token the driver should refuse outright, as opposed to a card that declines.
     */
    protected function rejectedToken(): string
    {
        return 'tok_error_invalid';
    }

    /**
     * A charge description that makes the driver's test mode decline.
     */
    protected function decliningDescription(): string
    {
        return 'decline this charge';
    }

    public function test_it_registers_a_customer_and_returns_a_reference(): void
    {
        $reference = $this->gateway()->createCustomer('Happy Paws Grooming', 'owner@example.com');

        $this->assertNotSame('', $reference);
    }

    public function test_it_exchanges_a_token_for_a_storable_payment_method(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');

        $details = $gateway->storePaymentMethod($customer, $this->acceptableToken());

        $this->assertNotSame('', $details->token);
        $this->assertNotSame('', $details->brand);

        // Exactly four digits, and they are all a receipt should ever show.
        $this->assertMatchesRegularExpression('/^\d{4}$/', $details->lastFour);

        $this->assertGreaterThanOrEqual(1, $details->expiryMonth);
        $this->assertLessThanOrEqual(12, $details->expiryMonth);
    }

    /**
     * The returned token must be the gateway's own handle, not the single-use token we sent.
     * A driver that echoed the input back would store something that cannot be charged twice.
     */
    public function test_the_stored_token_is_not_the_token_it_was_given(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');

        $token = $this->acceptableToken();
        $details = $gateway->storePaymentMethod($customer, $token);

        $this->assertNotSame($token, $details->token);
    }

    public function test_a_refused_token_raises_a_gateway_failure(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');

        $this->expectException(GatewayFailure::class);

        $gateway->storePaymentMethod($customer, $this->rejectedToken());
    }

    public function test_forgetting_a_payment_method_is_idempotent(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');
        $details = $gateway->storePaymentMethod($customer, $this->acceptableToken());

        $gateway->forgetPaymentMethod($customer, $details->token);

        // Removing it twice must not throw: webhooks are replayed and retries happen.
        $gateway->forgetPaymentMethod($customer, $details->token);

        $this->addToAssertionCount(1);
    }

    public function test_a_successful_charge_reports_the_amount_it_took(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');
        $gateway->storePaymentMethod($customer, $this->acceptableToken());

        $result = $gateway->charge($customer, 14_900, 'USD', 'Monthly subscription');

        $this->assertTrue($result->successful);
        $this->assertFalse($result->failed());
        $this->assertSame(14_900, $result->amountCents);
        $this->assertNotSame('', $result->reference);
        $this->assertNull($result->failureCode);
    }

    /**
     * The rule that keeps the dunning cycle of spec §24 workable: a decline is a result, not
     * an exception. A driver that threw on a declined card would turn an ordinary expired
     * card into a 500 and skip the retry logic entirely.
     */
    public function test_a_declined_charge_is_a_result_and_not_an_exception(): void
    {
        $gateway = $this->gateway();
        $customer = $gateway->createCustomer('Happy Paws Grooming', 'owner@example.com');
        $gateway->storePaymentMethod($customer, $this->acceptableToken());

        $result = $gateway->charge($customer, 14_900, 'USD', $this->decliningDescription());

        $this->assertFalse($result->successful);
        $this->assertTrue($result->failed());
        $this->assertNotNull($result->failureCode);
        $this->assertNotSame('', (string) $result->failureMessage);

        // Still reports what it tried to take, so the invoice can record the attempt.
        $this->assertSame(14_900, $result->amountCents);
    }
}
