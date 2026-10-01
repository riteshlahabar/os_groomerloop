<?php

namespace Modules\Booking;

use App\Support\ModuleServiceProvider;
use Modules\Booking\Services\PoliciesVerifier;
use Modules\Onboarding\Services\StepVerifiers;

/**
 * Booking owns the public-facing half of appointments (spec §12) — the booking widget a
 * stranger reaches by URL, and the settings (lead time, cancellation window, confirmation mode)
 * an owner configures it with.
 *
 * It depends on Scheduling's `AppointmentScheduler` (D-023 — it is the entry point onto
 * Scheduling's engine, never a second one), Catalog's `ServiceCatalog`, Team's `StaffDirectory`,
 * Crm's `CustomerDirectory` and Pets' `PetDirectory`, plus Onboarding's verifier registry, so it
 * boots after all of them.
 */
final class BookingServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Spec §7 step 6. Declared verified since Phase 4 with nothing registered to answer it.
        $this->app->make(StepVerifiers::class)
            ->register($this->app->make(PoliciesVerifier::class));
    }
}
