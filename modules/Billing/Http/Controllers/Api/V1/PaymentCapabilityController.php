<?php

namespace Modules\Billing\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Billing\Services\CardEntry;

/**
 * What the browser can do about payment methods on this installation (spec §24).
 *
 * The `/admin/billing` screen calls this before it renders its card form, the same way
 * `/admin/messages` calls `notifications/delivery-mode` instead of being told whether email
 * sends for real (invariant #5). Without it the page would have to read config itself, which
 * a client-side page cannot, or hard-code a provider name, which invariant #5 forbids.
 *
 * `card_entry` is the name of a client integration, not a provider name a caller should branch
 * business logic on. `publishable_key` is public by design — it can create tokens and nothing
 * else; the secret key never leaves the server.
 */
final class PaymentCapabilityController
{
    public function __invoke(CardEntry $cardEntry): JsonResponse
    {
        return response()->json([
            'data' => [
                'card_entry' => $cardEntry->mode(),
                'publishable_key' => $cardEntry->publishableKey(),
            ],
        ]);
    }
}
