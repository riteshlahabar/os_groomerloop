<?php

namespace Modules\Billing\Tests\Feature;

use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Tests\Contract\PaymentGatewayContract;
use ReflectionClass;
use Tests\TestCase;

/**
 * CI guard #6: every provider driver, fakes included, passes its shared contract test.
 *
 * The contract test only protects invariant #5 if every driver is actually subjected to it.
 * Adding a Stripe driver and forgetting its test case would leave the suite green while the
 * only driver that handles real money is unverified — so this discovers the drivers and
 * fails naming any that has no contract-bound test.
 */
final class GatewayDriverGuardTest extends TestCase
{
    public function test_every_payment_gateway_driver_has_a_contract_test(): void
    {
        $drivers = $this->discoverDrivers();

        $this->assertNotEmpty(
            $drivers,
            'No PaymentGateway drivers were discovered, so this guard is not looking.'
        );

        $covered = $this->driversCoveredByContractTests();

        $uncovered = array_values(array_diff($drivers, $covered));

        $this->assertSame([], $uncovered, sprintf(
            "These PaymentGateway drivers have no test using the shared contract:\n  - %s\n\n"
            .'Add a test case that uses the PaymentGatewayContract trait and returns the driver '
            .'from gateway(). A driver that is not held to the contract cannot be swapped in '
            .'safely (invariant #5).',
            implode("\n  - ", $uncovered)
        ));
    }

    /**
     * @return list<class-string<PaymentGateway>>
     */
    private function discoverDrivers(): array
    {
        $drivers = [];

        foreach (glob(base_path('modules/Billing/Services/Gateways/*.php')) ?: [] as $file) {
            $class = 'Modules\\Billing\\Services\\Gateways\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(PaymentGateway::class)) {
                continue;
            }

            $drivers[] = $class;
        }

        sort($drivers);

        return $drivers;
    }

    /**
     * Drivers named by a test class that uses the shared contract trait.
     *
     * Matched by the driver class being mentioned in the test's source. Crude, but it cannot
     * produce a false pass the way reflecting over a private gateway() body could, and a
     * driver's test naming a different driver is not a mistake anyone makes silently.
     *
     * @return list<string>
     */
    private function driversCoveredByContractTests(): array
    {
        $covered = [];

        $testFiles = array_merge(
            glob(base_path('modules/Billing/Tests/Unit/*.php')) ?: [],
            glob(base_path('modules/Billing/Tests/Feature/*.php')) ?: [],
        );

        foreach ($testFiles as $file) {
            $source = file_get_contents($file);

            if ($source === false || ! str_contains($source, class_basename(PaymentGatewayContract::class))) {
                continue;
            }

            foreach ($this->discoverDrivers() as $driver) {
                if (str_contains($source, class_basename($driver))) {
                    $covered[] = $driver;
                }
            }
        }

        return array_values(array_unique($covered));
    }
}
