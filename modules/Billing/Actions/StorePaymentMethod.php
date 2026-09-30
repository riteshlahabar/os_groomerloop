<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Models\Tenant;

/**
 * Attach a card to a business (spec §24, "payment method management").
 *
 * The token arriving here was produced by the gateway's client SDK in the customer's own
 * browser. No card number reaches this application at any point, which is what keeps spec
 * §28 tractable: there is no PAN to encrypt, log or leak.
 */
final class StorePaymentMethod
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Tenant $tenant, string $token, bool $makeDefault = true): PaymentMethod
    {
        $customerReference = $this->customerReferenceFor($tenant);

        // Called before the transaction opens: a gateway round trip inside a transaction
        // holds locks for the length of a network call.
        $details = $this->gateway->storePaymentMethod($customerReference, $token);

        return DB::transaction(function () use ($details, $makeDefault, $customerReference): PaymentMethod {
            $first = ! PaymentMethod::query()->exists();

            if ($makeDefault || $first) {
                PaymentMethod::query()->update(['is_default' => false]);
            }

            $method = PaymentMethod::create([
                'gateway' => (string) config('billing.gateway', 'fake'),
                'token' => $details->token,
                'brand' => $details->brand,
                'last_four' => $details->lastFour,
                'expiry_month' => $details->expiryMonth,
                'expiry_year' => $details->expiryYear,
                'is_default' => $makeDefault || $first,
            ]);

            // The gateway customer reference is stored on the subscription so a later charge
            // knows who to bill. A business that adds a card before subscribing simply has
            // nothing to update yet.
            Subscription::query()->current()->update(['gateway_customer_id' => $customerReference]);

            // Brand and last four only. Never the token, and there is nothing else to leak.
            $this->audit->record('payment_method.added', $method, [
                'brand' => $details->brand,
                'last_four' => $details->lastFour,
            ]);

            return $method;
        });
    }

    /**
     * Reuse the business's existing gateway customer, or register it on first use.
     */
    private function customerReferenceFor(Tenant $tenant): string
    {
        $existing = Subscription::query()->current()->value('gateway_customer_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return $this->gateway->createCustomer(
            name: $tenant->name,
            email: (string) ($tenant->email ?? ''),
        );
    }
}
