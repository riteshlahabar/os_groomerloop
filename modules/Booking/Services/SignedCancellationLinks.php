<?php

namespace Modules\Booking\Services;

use Illuminate\Support\Facades\URL;
use Modules\Booking\Contracts\CancellationLinks;
use Modules\Tenancy\Support\TenantContext;
use RuntimeException;

/**
 * Signs the URL with the app's own key (Laravel's `signed` route middleware), rather than
 * minting and storing a token column on `appointments` — nothing to migrate onto a model owned
 * by another module (Scheduling), and nothing to clean up. `URL::signedRoute()` is called with
 * no expiration: the link does not time out on its own, because the appointment's own status and
 * start time already make an old link stop working the moment it would matter (see
 * `CancelPublicBooking::eligibility()`).
 */
final class SignedCancellationLinks implements CancellationLinks
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function urlFor(int $appointmentId): string
    {
        $tenant = $this->tenant->tenant();

        if ($tenant === null) {
            // Every caller of this class runs inside a request or event already carrying a
            // resolved tenant (the booking flow, or a notification listener firing from it) —
            // reaching this means something is being called outside that context entirely.
            throw new RuntimeException('Cannot build a cancellation link with no tenant in context.');
        }

        return URL::signedRoute('public-booking.cancel.show', [
            'tenant' => $tenant->slug,
            'appointment' => $appointmentId,
        ]);
    }
}
