<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Contracts\TestMailSender;
use Modules\SuperAdmin\Http\Requests\SendTenantTestEmailRequest;
use Modules\SuperAdmin\Http\Requests\UpdateTenantMailSettingsRequest;
use Modules\SuperAdmin\Http\Resources\TenantMailSettingsResource;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * One business's own outbound SMTP account, configured by GroomerLoop staff (spec §13, §31;
 * `D-032`). The per-tenant counterpart to `PlatformMailSettingsController`.
 *
 * Every method does its work inside `TenantContext::runFor()` — the settings row is
 * tenant-scoped (invariant #1), the audit event takes its tenant from ambient context, and a
 * test message has to resolve the same configuration a real notification would. The pattern is
 * `PlatformTenantDetailResource`'s, which reads each tenant's billing state the same way.
 */
final class PlatformTenantMailSettingsController
{
    public function __construct(
        private readonly TenantContext $tenants,
    ) {}

    public function show(Tenant $tenant, TenantMailSettings $settings): TenantMailSettingsResource
    {
        return TenantMailSettingsResource::make(
            $this->tenants->runFor($tenant, static fn () => $settings->snapshotForCurrentTenant())
        );
    }

    public function update(
        UpdateTenantMailSettingsRequest $request,
        Tenant $tenant,
        TenantMailSettings $settings,
    ): TenantMailSettingsResource {
        $attributes = $request->settingsAttributes();

        return TenantMailSettingsResource::make(
            $this->tenants->runFor($tenant, static fn () => $settings->updateForCurrentTenant($attributes))
        );
    }

    /**
     * Send a test message through whatever this business currently resolves to — its own
     * account, the platform's, or the log driver. The only end-to-end proof available without
     * waiting for a real booking.
     */
    public function sendTest(
        SendTenantTestEmailRequest $request,
        Tenant $tenant,
        TestMailSender $sender,
        TenantMailSettings $settings,
    ): JsonResponse {
        $to = $request->recipient();

        /** @var array{bool, bool} $result */
        $result = $this->tenants->runFor($tenant, static fn (): array => [
            $settings->isLiveForCurrentTenant(),
            $sender->send($to),
        ]);

        [$live, $accepted] = $result;

        // The log driver answers true as happily as a real server does — that is the point of
        // the provider abstraction (invariant #5). So the wording here is decided by whether
        // anything is configured at all, never by the bool alone: an admin told "accepted" when
        // the message went to a log file would stop looking for the problem.
        $message = match (true) {
            ! $live => 'Nothing was delivered. Neither this business nor the platform has a mail '
                .'account configured, so the test message was written to the application log.',
            // Deliberately modest even on success: a server accepting a message is not the same
            // as a human finding it, and claiming otherwise is how an admin stops checking the
            // spam folder.
            $accepted => 'The mail server accepted the test message. Check the inbox to confirm it arrived.',
            default => 'The mail server refused the test message. See the application log for the reason.',
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
