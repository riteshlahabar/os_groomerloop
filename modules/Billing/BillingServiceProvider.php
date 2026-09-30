<?php

namespace Modules\Billing;

use App\Support\ModuleServiceProvider;
use Modules\Billing\Console\ExpireLapsedSubscriptionsCommand;
use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Services\Gateways\FakePaymentGateway;
use RuntimeException;

/**
 * Billing owns the SaaS lifecycle of spec §24 — subscriptions, invoices, payment methods and
 * the dunning cycle.
 *
 * It depends on Entitlements through the PlanRegistry contract and never touches the Plan
 * model or tenants.plan_id itself (D-007). That direction is what keeps spec §35's "billing
 * and entitlement state remain synchronized" enforceable: Billing decides when a plan
 * changes, Entitlements decides what it means, and there is exactly one writer.
 */
final class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * Payment gateway drivers, resolved by the `billing.gateway` config key.
     *
     * The fake is a real driver that passes the same contract test as every other
     * (CI guard #6) — not a stub. Stripe is added as one more entry here and needs no change
     * anywhere else in the product, which is invariant #5 working as intended.
     *
     * @var array<string, class-string<PaymentGateway>>
     */
    private const GATEWAYS = [
        'fake' => FakePaymentGateway::class,
    ];

    public function register(): void
    {
        parent::register();

        // Singleton so the fake's recorded charges survive across a request in tests, and so
        // a real driver's HTTP client and credentials are built once per process.
        $this->app->singleton(PaymentGateway::class, function (): PaymentGateway {
            $driver = (string) config('billing.gateway', 'fake');

            $class = self::GATEWAYS[$driver] ?? null;

            // An unknown driver fails at boot rather than silently falling back to the fake.
            // A production deploy that quietly stopped taking real money would be discovered
            // by the finance team, weeks later.
            if ($class === null) {
                throw new RuntimeException(sprintf(
                    'Unknown payment gateway [%s]. Configured drivers: %s.',
                    $driver,
                    implode(', ', array_keys(self::GATEWAYS))
                ));
            }

            return $this->app->make($class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireLapsedSubscriptionsCommand::class]);
        }
    }
}
