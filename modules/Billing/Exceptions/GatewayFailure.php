<?php

namespace Modules\Billing\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The payment provider could not be reached, or refused the request for a reason that is our
 * fault rather than the customer's.
 *
 * Distinct from a declined card, which is a ChargeResult. This is "the gateway is down" or
 * "our API key is wrong" — not something the customer can fix by using another card, and not
 * something to report as a payment failure on their account.
 */
final class GatewayFailure extends RuntimeException
{
    public static function unreachable(string $driver, string $detail = ''): self
    {
        return new self(trim("The {$driver} payment gateway could not be reached. {$detail}"));
    }

    public static function rejected(string $driver, string $detail): self
    {
        return new self("The {$driver} payment gateway rejected the request: {$detail}");
    }

    public function render(Request $request): JsonResponse
    {
        // 502, not 400: nothing the caller sent is wrong. Surfacing this as a validation
        // error would have the SPA tell a groomer their card details are invalid when the
        // real problem is on our side.
        return response()->json([
            'message' => 'Payment processing is temporarily unavailable. Please try again shortly.',
        ], Response::HTTP_BAD_GATEWAY);
    }
}
