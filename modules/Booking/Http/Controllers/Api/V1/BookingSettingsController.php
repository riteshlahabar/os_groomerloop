<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Booking\Actions\SetBookingSettings;
use Modules\Booking\Http\Requests\SetBookingSettingsRequest;
use Modules\Booking\Http\Resources\BookingSettingsResource;
use Modules\Booking\Models\BookingSettings;

/**
 * Spec §12's configurable half — a singleton resource, the same shape `BusinessProfile` and
 * `BusinessHours` use. No {id} in the route; the tenant scope answers "whose settings".
 */
final class BookingSettingsController
{
    public function show(): JsonResponse
    {
        $settings = BookingSettings::query()->first();

        // Null, not a default-filled object: a business that has not reached this step yet has
        // no settings, and the setup screen renders that as a normal state, not a missing page.
        // SubmitPublicBooking still applies the documented defaults when no row exists.
        return response()->json([
            'data' => $settings === null ? null : BookingSettingsResource::make($settings)->resolve(),
        ]);
    }

    public function update(SetBookingSettingsRequest $request, SetBookingSettings $set): BookingSettingsResource
    {
        return BookingSettingsResource::make($set->execute($request->settingsAttributes()));
    }
}
