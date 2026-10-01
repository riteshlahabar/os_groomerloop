<?php

namespace Modules\Team;

use App\Support\ModuleServiceProvider;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Services\EloquentStaffDirectory;
use Modules\Team\Services\StaffVerifier;

/**
 * Team owns who grooms (spec §23, and the "Staff/Groomer" resource of §26).
 *
 * It depends on Tenancy, Audit, Onboarding's verifier registry and Catalog's ServiceCatalog
 * contract (D-017) — so it boots after Catalog. Scheduling (§11) and Booking (§12) will depend
 * on *this* module, through StaffDirectory.
 */
final class TeamServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Singleton so the per-request memo is shared: the scheduler asks about the same
        // staff member repeatedly while validating one appointment.
        $this->app->singleton(EloquentStaffDirectory::class);
        $this->app->alias(EloquentStaffDirectory::class, StaffDirectory::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Spec §7 step 4. The last of the four verified steps that had no module to answer it.
        $this->app->make(StepVerifiers::class)
            ->register($this->app->make(StaffVerifier::class));
    }
}
