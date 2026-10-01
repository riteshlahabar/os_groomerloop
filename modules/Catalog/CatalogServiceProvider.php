<?php

namespace Modules\Catalog;

use App\Support\ModuleServiceProvider;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Catalog\Services\EloquentServiceCatalog;
use Modules\Catalog\Services\ServicesVerifier;
use Modules\Onboarding\Services\StepVerifiers;

/**
 * Catalog owns what the business sells (spec §10, §26).
 *
 * It depends on Tenancy, Audit and Onboarding's verifier registry, and on nothing else — notably not
 * on Team. §10 lists "eligible groomers/staff" and that link is owned by Team instead (`D-017`),
 * because Catalog ships first and cannot validate a staff id that has no table yet.
 *
 * Scheduling (§11), Booking (§12), Team (§23) and Insights (§16) will all depend on *this* module,
 * through `ServiceCatalog`.
 */
final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Singleton so the per-request memo is shared: the scheduler asks about the same service
        // repeatedly while validating one appointment.
        $this->app->singleton(EloquentServiceCatalog::class);
        $this->app->alias(EloquentServiceCatalog::class, ServiceCatalog::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Spec §7 step 5. The step has read `unavailable` since Phase 4 because nothing could answer
        // it; Catalog can, and it is a *required* step, so this is what stands between a business and
        // `is_ready`.
        $this->app->make(StepVerifiers::class)
            ->register($this->app->make(ServicesVerifier::class));
    }
}
