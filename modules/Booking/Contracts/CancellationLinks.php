<?php

namespace Modules\Booking\Contracts;

/**
 * The only way another module (Notifications) learns a customer's self-service cancel link —
 * never by constructing `route('public-booking.cancel.show', ...)` itself, which would bake
 * Booking's route name into a module that has no other reason to know it.
 */
interface CancellationLinks
{
    /**
     * A durable, signed URL a customer can open with no login to view and cancel one
     * appointment. Not time-limited by the signature itself — the appointment's own status and
     * start time, checked server-side on every visit, are what make an old link stop working.
     */
    public function urlFor(int $appointmentId): string;
}
