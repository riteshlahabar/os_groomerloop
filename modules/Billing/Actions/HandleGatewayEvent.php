<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Billing\Domain\GatewayEvent;
use Modules\Billing\Domain\GatewayEventType;
use Modules\Billing\Domain\InvoiceStatus;
use Modules\Billing\Models\GatewayEventLog;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * Apply one inbound gateway event (spec §24, §30).
 *
 * Provider-neutral: it takes a translated `GatewayEvent` and never learns which gateway sent
 * it, which is invariant #5 on the inbound path. Every provider-shaped concern — signature
 * verification, event names, payload shapes — stays in that provider's translator.
 *
 * **Tenancy.** A webhook arrives with no tenant context at all, so this is one of the few
 * places allowed to query across tenants: the event names a *gateway* customer or card, and
 * `scopeAcrossAllTenants()` (documented for exactly platform-level work like this) is how the
 * owning tenant is found. Everything after that runs inside `TenantContext::runFor()`, so the
 * actions it delegates to see a normal, scoped world and write correctly scoped audit events.
 *
 * **Nothing is invented.** An event naming a charge or card this installation has no record of
 * is recorded as `unmatched` and changes no state — it is never turned into a new invoice, nor
 * allowed to move a subscription's dunning state on the strength of a charge we cannot tie to
 * an invoice we raised. Refunds and disputes are recorded and otherwise left alone, because
 * this product has no refund or dispute workflow and half of one would be worse than a gap.
 */
final class HandleGatewayEvent
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
        private readonly RecordPaymentSuccess $successes,
        private readonly RecordPaymentFailure $failures,
    ) {}

    /**
     * @return string one of GatewayEventLog's STATUS_* values — what was done, for the receipt
     */
    public function execute(string $provider, GatewayEvent $event): string
    {
        // Replays and backlog deliveries. The unique index is the real guard; this read keeps
        // the common case out of an exception path.
        if ($this->alreadyHandled($provider, $event->id)) {
            return GatewayEventLog::STATUS_IGNORED;
        }

        [$status, $note] = $this->apply($event);

        $this->record($provider, $event, $status, $note);

        return $status;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function apply(GatewayEvent $event): array
    {
        return match ($event->type) {
            GatewayEventType::ChargeSucceeded => $this->chargeSucceeded($event),
            GatewayEventType::ChargeFailed => $this->chargeFailed($event),

            // Recorded only — see the class docblock. Named in the note so the row says which.
            GatewayEventType::ChargeRefunded,
            GatewayEventType::ChargeDisputed => [
                GatewayEventLog::STATUS_IGNORED,
                'No refund or dispute workflow exists in this product; recorded only.',
            ],

            GatewayEventType::PaymentMethodUpdated => $this->paymentMethodUpdated($event),
            GatewayEventType::PaymentMethodDetached => $this->paymentMethodDetached($event),

            null => [GatewayEventLog::STATUS_IGNORED, 'Event type is not one this product acts on.'],
        };
    }

    /**
     * A charge settled. Mark its invoice paid and clear the subscription's dunning state —
     * the "late success" case `RecordPaymentSuccess`'s own docblock anticipates.
     *
     * Already-paid is `ignored`, not an error: a gateway can describe one payment with two
     * different events (so idempotency by event id cannot catch it), and the synchronous
     * charge that started this may have recorded the success before the webhook arrived.
     */
    private function chargeSucceeded(GatewayEvent $event): array
    {
        $invoice = $this->invoiceFor($event->chargeReference);

        if ($invoice === null) {
            return [
                GatewayEventLog::STATUS_UNMATCHED,
                'No invoice carries this charge reference.',
            ];
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            return [GatewayEventLog::STATUS_IGNORED, 'That invoice is already paid.'];
        }

        return $this->forTenantOf($invoice->tenant_id, function () use ($invoice): array {
            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            $this->audit->record('invoice.paid', $invoice, [
                'number' => $invoice->number,
                'total_cents' => $invoice->total_cents,
                'source' => 'gateway_webhook',
            ]);

            $subscription = $this->subscriptionFor($invoice);

            if ($subscription !== null) {
                $this->successes->execute($subscription);
            }

            return [GatewayEventLog::STATUS_APPLIED, null];
        });
    }

    /**
     * A charge did not settle — including one that needed authentication nobody completed,
     * which is the whole reason an off-session charge can fail after the request that made it.
     *
     * An invoice that already carries a failure code is `ignored`: the synchronous path
     * records the same failure, and a second pass would increment the dunning counter twice
     * for one non-payment.
     */
    private function chargeFailed(GatewayEvent $event): array
    {
        $invoice = $this->invoiceFor($event->chargeReference);

        if ($invoice === null) {
            return [
                GatewayEventLog::STATUS_UNMATCHED,
                'No invoice carries this charge reference.',
            ];
        }

        if ($invoice->status === InvoiceStatus::Paid) {
            return [GatewayEventLog::STATUS_IGNORED, 'That invoice is already paid.'];
        }

        if ($invoice->failure_code !== null) {
            return [GatewayEventLog::STATUS_IGNORED, 'That failure is already recorded.'];
        }

        $code = $event->failureCode ?? 'card_declined';

        return $this->forTenantOf($invoice->tenant_id, function () use ($invoice, $event, $code): array {
            $invoice->forceFill([
                'failure_code' => $code,
                'failure_message' => $event->failureMessage ?? 'The charge was declined.',
            ])->save();

            $this->audit->record('invoice.payment_failed', $invoice, [
                'number' => $invoice->number,
                'code' => $code,
                'source' => 'gateway_webhook',
            ]);

            $subscription = $this->subscriptionFor($invoice);

            if ($subscription !== null) {
                $this->failures->execute($subscription, $code);
            }

            return [GatewayEventLog::STATUS_APPLIED, null];
        });
    }

    /**
     * The card itself changed at the gateway — a reissued number, a new expiry. There is no
     * way to learn this except here, and the billing screen would otherwise show the old last
     * four indefinitely and badge a perfectly good card as expired.
     */
    private function paymentMethodUpdated(GatewayEvent $event): array
    {
        if ($event->card === null) {
            return [GatewayEventLog::STATUS_IGNORED, 'The event carried no card details.'];
        }

        $method = $this->paymentMethodFor($event->paymentMethodToken);

        if ($method === null) {
            return [GatewayEventLog::STATUS_UNMATCHED, 'No stored card carries this token.'];
        }

        $card = $event->card;

        $unchanged = $method->brand === $card->brand
            && $method->last_four === $card->lastFour
            && $method->expiry_month === $card->expiryMonth
            && $method->expiry_year === $card->expiryYear;

        if ($unchanged) {
            return [GatewayEventLog::STATUS_IGNORED, 'The stored card already matches.'];
        }

        return $this->forTenantOf($method->tenant_id, function () use ($method, $card): array {
            $before = $method->brand.' ending '.$method->last_four;

            $method->forceFill([
                'brand' => $card->brand,
                'last_four' => $card->lastFour,
                'expiry_month' => $card->expiryMonth,
                'expiry_year' => $card->expiryYear,
            ])->save();

            // Brand and last four only, as everywhere else that audits a card.
            $this->audit->record('payment_method.updated_by_gateway', $method, [
                'before' => $before,
                'after' => $card->describe(),
            ]);

            return [GatewayEventLog::STATUS_APPLIED, null];
        });
    }

    /**
     * The card is gone at the gateway, so the local row is a phantom: the billing screen would
     * offer it and the next charge would fail on a card that does not exist.
     *
     * Deliberately **not** routed through `ForgetPaymentMethod`, which refuses to remove the
     * last card on a live subscription and says so to the owner. That refusal is right for a
     * person clicking Remove and wrong here — the card is already gone whether this
     * application agrees or not, and refusing would leave the phantom in place. The default is
     * re-promoted the same way that action does it, for the same reason: a business with cards
     * but no default has nothing for the next charge to pick.
     */
    private function paymentMethodDetached(GatewayEvent $event): array
    {
        $method = $this->paymentMethodFor($event->paymentMethodToken);

        if ($method === null) {
            // Already removed here, or never stored — the end state the event describes.
            return [GatewayEventLog::STATUS_IGNORED, 'No stored card carries this token.'];
        }

        return $this->forTenantOf($method->tenant_id, function () use ($method): array {
            DB::transaction(function () use ($method): void {
                $wasDefault = $method->is_default;

                $this->audit->record('payment_method.removed', $method, [
                    'brand' => $method->brand,
                    'last_four' => $method->last_four,
                    'source' => 'gateway_webhook',
                ]);

                $method->delete();

                if ($wasDefault) {
                    PaymentMethod::query()->oldest('id')->first()?->forceFill(['is_default' => true])->save();
                }
            });

            return [GatewayEventLog::STATUS_APPLIED, null];
        });
    }

    /**
     * Across every tenant, deliberately: the reference is a gateway id, and which tenant owns
     * it is what we are trying to find out. `scopeAcrossAllTenants()` exists for this.
     */
    private function invoiceFor(?string $chargeReference): ?Invoice
    {
        if ($chargeReference === null) {
            return null;
        }

        return Invoice::query()
            ->acrossAllTenants()
            ->where('gateway_charge_id', $chargeReference)
            ->latest('id')
            ->first();
    }

    private function paymentMethodFor(?string $token): ?PaymentMethod
    {
        if ($token === null) {
            return null;
        }

        return PaymentMethod::query()
            ->acrossAllTenants()
            ->where('token', $token)
            ->first();
    }

    /**
     * The invoice's own subscription, read across tenants for the same reason, and only used
     * inside `runFor()` so the actions it is handed to behave normally.
     */
    private function subscriptionFor(Invoice $invoice): ?Subscription
    {
        if ($invoice->subscription_id === null) {
            return null;
        }

        return Subscription::query()
            ->acrossAllTenants()
            ->whereKey($invoice->subscription_id)
            ->first();
    }

    /**
     * @param  callable(): array{0: string, 1: ?string}  $callback
     * @return array{0: string, 1: ?string}
     */
    private function forTenantOf(?int $tenantId, callable $callback): array
    {
        $tenant = $tenantId === null ? null : Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return [
                GatewayEventLog::STATUS_UNMATCHED,
                'The matching record names a tenant that no longer exists.',
            ];
        }

        return $this->tenants->runFor($tenant, $callback);
    }

    private function alreadyHandled(string $provider, string $eventId): bool
    {
        return GatewayEventLog::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->exists();
    }

    private function record(string $provider, GatewayEvent $event, string $status, ?string $note): void
    {
        GatewayEventLog::create([
            'provider' => $provider,
            'event_id' => $event->id,
            'provider_type' => mb_substr($event->providerType, 0, 64),
            'type' => $event->type?->value,
            'status' => $status,
            'note' => $note === null ? null : mb_substr($note, 0, 255),
            'received_at' => now(),
        ]);
    }
}
