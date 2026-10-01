<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;

/**
 * Remove a stored card (spec §24).
 */
final class ForgetPaymentMethod
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(PaymentMethod $method): void
    {
        $this->refuseIfItIsTheOnlyCardOnALiveSubscription($method);

        $customerReference = Subscription::query()->current()->value('gateway_customer_id');

        if (is_string($customerReference) && $customerReference !== '') {
            $this->gateway->forgetPaymentMethod($customerReference, $method->token);
        }

        $newDefault = DB::transaction(function () use ($method): ?PaymentMethod {
            $wasDefault = $method->is_default;

            $this->audit->record('payment_method.removed', $method, [
                'brand' => $method->brand,
                'last_four' => $method->last_four,
            ]);

            $method->delete();

            // Never leave a business with cards but no default, or the next charge has
            // nothing to pick and fails for a reason nobody can see on the billing screen.
            if (! $wasDefault) {
                return null;
            }

            $next = PaymentMethod::query()->oldest('id')->first();
            $next?->forceFill(['is_default' => true])->save();

            return $next;
        });

        // Outside the transaction, same reason as StorePaymentMethod's equivalent call: keeps
        // a real driver's own default payment method in sync with the new local one.
        if ($newDefault !== null && is_string($customerReference) && $customerReference !== '') {
            $this->gateway->setDefaultPaymentMethod($customerReference, $newDefault->token);
        }
    }

    /**
     * A live subscription with no card is a payment failure waiting to happen, and it would
     * arrive as a decline the customer did not expect. Better to refuse here and say why.
     */
    private function refuseIfItIsTheOnlyCardOnALiveSubscription(PaymentMethod $method): void
    {
        $others = PaymentMethod::query()->whereKeyNot($method->getKey())->exists();

        if ($others) {
            return;
        }

        $hasLiveSubscription = Subscription::query()->current()->exists();

        if ($hasLiveSubscription) {
            throw ValidationException::withMessages([
                'payment_method' => 'Add another payment method before removing the last one, '
                    .'or cancel the subscription first.',
            ]);
        }
    }
}
