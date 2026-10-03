<?php

namespace Modules\Notifications\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Notifications\Contracts\TenantMailSettings;

/**
 * Whether messages for this business actually leave the server (spec §13, `D-032`).
 *
 * Exists so the §13 Messages screen can stop hard-coding "no provider is connected yet". That
 * sentence was true of every tenant until per-tenant SMTP arrived and is now true of only some,
 * and a screen that tells an owner nothing was delivered when it was — or the reverse — is the
 * exact failure invariant #5 is written to prevent.
 *
 * Reports *capability*, never which provider or whose account: one bool per channel is all a
 * caller above the provider interface is allowed to know.
 */
final class MailDeliveryModeController
{
    public function __invoke(TenantMailSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => [
                'email_live' => $settings->isLiveForCurrentTenant(),
                // No SMS driver exists yet — `LogSmsProvider` reaches nobody (`D-025`).
                'sms_live' => false,
            ],
        ]);
    }
}
