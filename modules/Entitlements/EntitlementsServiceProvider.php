<?php

namespace Modules\Entitlements;

use App\Support\ModuleServiceProvider;
use Illuminate\Routing\Router;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Entitlements\Http\Middleware\EnsureEntitlement;
use Modules\Entitlements\Services\DatabasePlanRegistry;
use Modules\Entitlements\Services\PlanEntitlements;

/**
 * Entitlements owns the plan catalog and the single answer to "may this business use this"
 * (invariant #3, spec §2 and §25).
 *
 * It depends on Tenancy, because the question is always about the current business. It does
 * NOT depend on Billing, and that direction matters: entitlements have to work before any
 * payment exists — during registration, during a trial, and after a subscription ends — so
 * Billing is the module that writes tenants.plan_id, and this module only reads it.
 */
final class EntitlementsServiceProvider extends ModuleServiceProvider
{
    /**
     * One entitlement service per request or job.
     *
     * Bound as a singleton and then *aliased* to the contract, rather than registering both
     * names in $singletons. Two singleton bindings to the same class produce two separate
     * instances, so a caller type-hinting the interface and a caller type-hinting the concrete
     * class would each get their own memo — and a plan change flushed on one would leave the
     * other answering from the stale one. An alias makes both names the same object.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(PlanEntitlements::class);
        $this->app->alias(PlanEntitlements::class, Entitlements::class);

        $this->app->bind(PlanRegistry::class, DatabasePlanRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Registered by the module that owns it, beside `permission:` from Identity.
        $this->app->make(Router::class)->aliasMiddleware('entitlement', EnsureEntitlement::class);
    }
}
