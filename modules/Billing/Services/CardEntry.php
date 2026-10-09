<?php

namespace Modules\Billing\Services;

/**
 * Which client-side card-entry integration the browser should use, if any (spec §24, §28).
 *
 * This exists for the same reason §13's `notifications/delivery-mode` endpoint does: invariant
 * #5 says nothing above `PaymentGateway` may learn which provider is in use, so the billing
 * page is not *told* that Stripe is live — it asks, gets back the name of an integration plus
 * the public key that integration needs, and renders accordingly. A second driver one day adds
 * a mode here and a branch in the page; no business logic moves.
 *
 * Card data never reaches this application (§28). The publishable key is public by design —
 * it can only create tokens, never read or charge anything — which is exactly why the secret
 * key is not exposed here and never leaves the server.
 */
final class CardEntry
{
    /** Stripe.js + Elements in the browser, tokenising straight to Stripe. */
    public const MODE_STRIPE_ELEMENTS = 'stripe_elements';

    /** No card can be entered: either no real gateway, or no publishable key for it. */
    public const MODE_UNAVAILABLE = 'unavailable';

    /**
     * The integration the page should load, or MODE_UNAVAILABLE.
     *
     * A `stripe` gateway with no publishable key is deliberately unavailable rather than
     * broken-looking: the server could charge a card already on file, but the browser has
     * nothing to tokenise a new one with, and a mounted-but-keyless Elements form fails with
     * a console error no owner would see.
     */
    public function mode(): string
    {
        if ($this->gateway() !== 'stripe') {
            return self::MODE_UNAVAILABLE;
        }

        return $this->publishableKey() === null
            ? self::MODE_UNAVAILABLE
            : self::MODE_STRIPE_ELEMENTS;
    }

    public function publishableKey(): ?string
    {
        $key = trim((string) config('services.stripe.key', ''));

        return $key === '' ? null : $key;
    }

    public function gateway(): string
    {
        return (string) config('billing.gateway', 'fake');
    }
}
