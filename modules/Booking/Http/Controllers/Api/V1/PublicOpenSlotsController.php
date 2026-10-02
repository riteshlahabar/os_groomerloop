<?php

namespace Modules\Booking\Http\Controllers\Api\V1;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Modules\Booking\Http\Requests\PublicOpenSlotsRequest;
use Modules\Booking\Models\BookingSettings;
use Modules\Scheduling\Contracts\AppointmentScheduler;

/**
 * Every start time a stranger may pick on one day — spec §12's "select available date/time"
 * step, reached only through Scheduling's contract (D-023), the same as every other public
 * endpoint in this module. Without this, a client could only learn a day's open times by asking
 * `PublicAvailabilityController` once per candidate slot (the gap flagged in
 * docs/PROJECT_SUMMARY.md: "a client must fire a request per candidate slot").
 *
 * The lead-time filter is this module's own, never Scheduling's — the same split
 * `SubmitPublicBooking` already uses for a single slot: a time can be perfectly available and
 * still too soon for a stranger with nobody to call and confirm it by hand.
 */
final class PublicOpenSlotsController
{
    public function __invoke(PublicOpenSlotsRequest $request, AppointmentScheduler $scheduler): JsonResponse
    {
        $leadTimeMinutes = BookingSettings::query()->first()?->lead_time_minutes ?? 60;
        $earliest = now()->addMinutes($leadTimeMinutes);

        $slots = $scheduler->openSlotsFor($request->serviceId(), $request->staffMemberId(), $request->onDate());

        $slots = array_values(array_filter(
            $slots,
            fn (DateTimeImmutable $slot): bool => Carbon::instance($slot)->gte($earliest),
        ));

        return response()->json([
            'data' => array_map(
                fn (DateTimeImmutable $slot): string => Carbon::instance($slot)->toIso8601String(),
                $slots,
            ),
        ]);
    }
}
