<?php

namespace Modules\Notifications\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Contracts\TestMailSender;
use Modules\Notifications\Http\Requests\SendTestEmailRequest;
use Modules\Notifications\Http\Requests\UpdateTenantMailSettingsRequest;
use Modules\Notifications\Http\Resources\TenantMailSettingsResource;

/**
 * The business's own outbound email account, edited by its owner (spec §13, §7 step 7; `D-033`).
 *
 * Moved here from the §31 console: the sending domain is the business's own brand and
 * deliverability, so leaving it with GroomerLoop staff made a support ticket out of something an
 * owner should self-serve. No `runFor()` anywhere — the `tenant` middleware has already resolved
 * the business, and every contract method answers about the current tenant.
 */
final class TenantMailSettingsController
{
    public function show(TenantMailSettings $settings): TenantMailSettingsResource
    {
        return TenantMailSettingsResource::make($settings->snapshotForCurrentTenant());
    }

    public function update(
        UpdateTenantMailSettingsRequest $request,
        TenantMailSettings $settings,
    ): TenantMailSettingsResource {
        return TenantMailSettingsResource::make(
            $settings->updateForCurrentTenant($request->settingsAttributes())
        );
    }

    /**
     * Send a test message through whatever this business currently resolves to — its own
     * account, GroomerLoop's, or the log driver. The only end-to-end proof available without
     * waiting for a real booking.
     */
    public function sendTest(
        SendTestEmailRequest $request,
        TestMailSender $sender,
        TenantMailSettings $settings,
    ): JsonResponse {
        $live = $settings->isLiveForCurrentTenant();
        $accepted = $sender->send($request->recipient());

        // The log driver answers true as happily as a real server does — that is the point of
        // the provider abstraction (invariant #5). So the wording is decided by whether anything
        // is configured at all, never by the bool alone: an owner told "accepted" when the
        // message went to a log file would stop looking for the problem.
        $message = match (true) {
            ! $live => 'Nothing was delivered. No mail account is configured for your business yet, '
                .'so the test message was only recorded on the server.',
            // Deliberately modest even on success: a server accepting a message is not the same
            // as a human finding it, and claiming otherwise is how someone stops checking spam.
            $accepted => 'Your mail server accepted the test message. Check the inbox to confirm it arrived.',
            default => 'Your mail server refused the test message. Check the host, port and password below.',
        };

        return response()->json([
            'data' => [
                'accepted' => $accepted,
                'delivered' => $live && $accepted,
                'message' => $message,
            ],
        ], $live && ! $accepted ? 502 : 200);
    }
}
