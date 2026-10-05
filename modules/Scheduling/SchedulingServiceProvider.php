<?php

namespace Modules\Scheduling;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Scheduling\Contracts\AppointmentMetrics;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Policies\AppointmentPolicy;
use Modules\Scheduling\Services\BusinessHoursVerifier;
use Modules\Scheduling\Services\EloquentAppointmentMetrics;
use Modules\Scheduling\Services\EloquentAppointmentScheduler;

/**
 * Scheduling owns the appointment engine (spec §11), the core data-model link between Pets,
 * Catalog and Team and the entry point the future public booking phase builds on (`D-023`).
 *
 * It depends on Catalog's ServiceCatalog, Team's StaffDirectory, Crm's CustomerDirectory and
 * Pets' PetDirectory contracts, plus Onboarding's verifier registry — so it boots last, after
 * every module whose contract it reads.
 */
final class SchedulingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(EloquentAppointmentScheduler::class);
        $this->app->alias(EloquentAppointmentScheduler::class, AppointmentScheduler::class);

        $this->app->singleton(EloquentAppointmentMetrics::class);
        $this->app->alias(EloquentAppointmentMetrics::class, AppointmentMetrics::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Spec §7 step 1. Read `unavailable` since Phase 4 because nothing could answer it.
        $this->app->make(StepVerifiers::class)
            ->register($this->app->make(BusinessHoursVerifier::class));

        Gate::policy(Appointment::class, AppointmentPolicy::class);
    }
}
