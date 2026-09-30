<?php

namespace Modules\Billing\Tests\Unit;

use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Services\Gateways\FakePaymentGateway;
use Modules\Billing\Tests\Contract\PaymentGatewayContract;
use Tests\TestCase;

/**
 * The fake driver, held to exactly the same contract as a real one (CI guard #6).
 *
 * This class is deliberately almost empty. Everything it asserts comes from the shared
 * contract; a driver's own test case exists only to say which driver, and to override the
 * magic tokens its provider's test mode uses.
 */
final class FakePaymentGatewayTest extends TestCase
{
    use PaymentGatewayContract;

    private FakePaymentGateway $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new FakePaymentGateway;
    }

    protected function gateway(): PaymentGateway
    {
        return $this->driver;
    }

    /**
     * Beyond the contract: the fake records what it was asked to charge, which is what lets
     * the billing tests assert that the right amount reached the gateway at all.
     */
    public function test_it_records_the_charges_it_was_asked_to_make(): void
    {
        $customer = $this->driver->createCustomer('Happy Paws Grooming', 'owner@example.com');

        $this->driver->charge($customer, 24_900, 'USD', 'Monthly subscription');

        $this->assertCount(1, $this->driver->recordedCharges());
        $this->assertSame(24_900, $this->driver->recordedCharges()[0]['amount']);
    }
}
