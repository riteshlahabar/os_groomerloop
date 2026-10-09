<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Contracts\PaymentGateway;
use Modules\Billing\Domain\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\InvoiceNumbers;
use Modules\Entitlements\Domain\PlanSummary;

/**
 * Raise an invoice for a subscription period and try to collect it (spec §24).
 *
 * The invoice is written before the gateway is called, and survives whatever the gateway
 * says. A failed charge must leave a record — spec §24 keeps billing history and §35 requires
 * failures to be logged and retryable, neither of which works if a decline leaves nothing
 * behind but a status change.
 */
final class ChargeSubscription
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly InvoiceNumbers $numbers,
        private readonly AuditRecorder $audit,
        private readonly RecordPaymentFailure $failures,
        private readonly RecordPaymentSuccess $successes,
    ) {}

    public function execute(Subscription $subscription, PlanSummary $plan): Invoice
    {
        $invoice = $this->raiseInvoice($subscription, $plan);

        $customerReference = $subscription->gateway_customer_id;

        // No stored payment method is a failed collection, not an error: it is exactly the
        // state a trial ends in when the business never added a card, and it belongs in the
        // dunning cycle like any other non-payment.
        if ($customerReference === null || ! $this->hasPaymentMethod()) {
            // No reference to record: nothing was ever sent to the gateway.
            $this->markFailed($invoice, 'no_payment_method', 'No payment method on file.');
            $this->failures->execute($subscription, 'no_payment_method');

            return $invoice->refresh();
        }

        $result = $this->gateway->charge(
            customerReference: $customerReference,
            amountCents: $invoice->total_cents,
            currency: $invoice->currency,
            description: $invoice->description,
        );

        if ($result->failed()) {
            $this->markFailed(
                $invoice,
                $result->failureCode ?? 'declined',
                $result->failureMessage ?? 'Declined.',
                $result->reference,
            );
            $this->failures->execute($subscription, $result->failureCode ?? 'declined');

            return $invoice->refresh();
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Paid,
            'gateway_charge_id' => $result->reference,
            'paid_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        $this->audit->record('invoice.paid', $invoice, [
            'number' => $invoice->number,
            'total_cents' => $invoice->total_cents,
        ]);

        // A successful collection also clears any dunning state the subscription was in —
        // this is how a business that updated its card gets out of past_due.
        $this->successes->execute($subscription);

        return $invoice;
    }

    private function raiseInvoice(Subscription $subscription, PlanSummary $plan): Invoice
    {
        return DB::transaction(function () use ($subscription, $plan): Invoice {
            $invoice = Invoice::create([
                'subscription_id' => $subscription->getKey(),
                'number' => $this->numbers->next(),
                'status' => InvoiceStatus::Open,
                'description' => sprintf('%s plan — monthly subscription', $plan->name),
                'subtotal_cents' => $plan->priceCents,

                // Zero until finance rules on US SaaS sales tax (§40). The column exists so
                // adding it later is not a migration against a live invoices table.
                'tax_cents' => 0,
                'total_cents' => $plan->priceCents,
                'currency' => $plan->currency,

                // Denormalised: an invoice must always say what was charged, even after the
                // price list changes underneath it.
                'plan_key' => $plan->key,
                'plan_name' => $plan->name,
                'gateway' => $subscription->gateway,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) config('billing.invoice.due_days', 7)),
            ]);

            $this->audit->record('invoice.raised', $invoice, [
                'number' => $invoice->number,
                'total_cents' => $invoice->total_cents,
                'plan' => $plan->key,
            ]);

            return $invoice;
        });
    }

    /**
     * The gateway's own reference is stored on a failure as well as on a success (added
     * 2026-10-09 with the webhook endpoint, `D-050`). Without it a charge that fails now and
     * settles later — the `requires_action` case, where the customer completes 3DS after the
     * off-session attempt gave up — arrives as a webhook naming a charge no invoice admits to,
     * and has to be filed as unmatched. `markFailed` is also reached with no reference at all,
     * when nothing was ever sent to the gateway.
     */
    private function markFailed(Invoice $invoice, string $code, string $message, ?string $reference = null): void
    {
        $invoice->forceFill(array_filter([
            'failure_code' => $code,
            'failure_message' => $message,
            'gateway_charge_id' => $reference,
        ], static fn ($value): bool => $value !== null))->save();

        $this->audit->record('invoice.payment_failed', $invoice, [
            'number' => $invoice->number,
            'code' => $code,
        ]);
    }

    private function hasPaymentMethod(): bool
    {
        return PaymentMethod::query()->exists();
    }
}
