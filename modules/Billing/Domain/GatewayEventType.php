<?php

namespace Modules\Billing\Domain;

/**
 * The gateway-side facts this product does something about (spec §24, §30).
 *
 * Provider-neutral on purpose: a driver's own event names (`payment_intent.succeeded`,
 * `payment_method.automatically_updated`) are translated into these before any business logic
 * sees them, so `HandleGatewayEvent` never learns which gateway sent it — invariant #5 applied
 * to inbound traffic rather than outbound calls.
 *
 * Deliberately short. An event with no case here is recorded as received and otherwise
 * ignored, which is the honest treatment for something this product has no subject code for.
 */
enum GatewayEventType: string
{
    /** A charge we initiated has settled — possibly long after the request that started it. */
    case ChargeSucceeded = 'charge_succeeded';

    /** A charge we initiated did not settle, including one that needed 3DS nobody completed. */
    case ChargeFailed = 'charge_failed';

    /**
     * Money went back to the customer, or the customer's bank is reclaiming it. Recorded
     * only: this product has no refund or dispute workflow, and inventing a half of one is
     * worse than a visible gap.
     */
    case ChargeRefunded = 'charge_refunded';
    case ChargeDisputed = 'charge_disputed';

    /**
     * The stored card's own details changed at the gateway — the card-account-updater case,
     * where an issuer reissues a card and the gateway follows it. There is no way to learn
     * this except from a webhook, which is most of why this endpoint exists.
     */
    case PaymentMethodUpdated = 'payment_method_updated';

    /** The card is no longer on file at the gateway, however it got detached. */
    case PaymentMethodDetached = 'payment_method_detached';
}
