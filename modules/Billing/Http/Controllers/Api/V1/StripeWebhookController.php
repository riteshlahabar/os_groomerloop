<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Actions\HandleGatewayEvent;
use Modules\Billing\Exceptions\GatewayFailure;
use Modules\Billing\Services\Gateways\StripeWebhookTranslator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe's webhook endpoint (spec §24, §30).
 *
 * Unauthenticated by necessity — Stripe holds no session and no API token of ours — so the
 * **signature is the authentication**, and `StripeWebhookTranslator` refuses anything it
 * cannot verify, including when no signing secret is configured. Without that check anyone on
 * the internet could POST `charge.succeeded` and mark their own invoice paid.
 *
 * Status codes are chosen for Stripe's retry behaviour, not for a human reader:
 *
 *   - **200** for anything understood, including an event we deliberately do nothing about.
 *     A non-2xx would have Stripe retry an event that will never be handled, for days.
 *   - **400** for a payload or signature that does not verify. Stripe does not retry these,
 *     which is right: a forged or misconfigured request will not fix itself on the fourth try.
 *   - **500** is left to the framework. A genuine fault *should* be retried, and Stripe's own
 *     backoff is a better queue than anything this application has (`D-011`: there is no
 *     worker on this host).
 *
 * The response body is deliberately uninformative. It is read by a machine, and an endpoint
 * that reports "no invoice carries this charge reference" to an unauthenticated caller is
 * telling a stranger what this installation knows. The detail goes to the `gateway_events`
 * receipt row instead.
 */
final class StripeWebhookController
{
    public function __invoke(Request $request, HandleGatewayEvent $handle): JsonResponse
    {
        $translator = new StripeWebhookTranslator(
            (string) config('services.stripe.webhook_secret', '')
        );

        try {
            // getContent(), never $request->all(): the signature is computed over the exact
            // bytes Stripe sent, and anything that re-encodes the JSON invalidates it.
            $event = $translator->translate(
                $request->getContent(),
                $request->header('Stripe-Signature'),
            );
        } catch (GatewayFailure $e) {
            report($e);

            return response()->json(['message' => 'Rejected.'], Response::HTTP_BAD_REQUEST);
        }

        $handle->execute('stripe', $event);

        return response()->json(['message' => 'Received.']);
    }
}
