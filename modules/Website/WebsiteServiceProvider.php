<?php

namespace Modules\Website;

use App\Support\ModuleServiceProvider;
use Modules\Website\Services\SiteComposer;
use Modules\Website\Services\WebsiteProvisioner;

/**
 * Website owns the tenant's own public site (spec §14, the "Website" entity of §26).
 *
 * It consumes four modules and owns none of their data: Catalog's `ServiceCatalog` for the service
 * list, Team's `StaffDirectory` for the groomers, Onboarding's `BusinessProfileDirectory` for the
 * business's name and address, and Tenancy for the slug in the URL. Nothing is copied into website
 * content, so a published page cannot advertise a retired service or a groomer who has left (D-007).
 *
 * It does not own booking either: every call to action on a tenant site links to §12's
 * `/book/{tenant}` wizard, so there is exactly one booking implementation in the product
 * (invariant #2, D-023).
 */
final class WebsiteServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Singletons so one request that renders a page and its navigation resolves the site, the
        // services, the staff and the business profile once each.
        $this->app->singleton(WebsiteProvisioner::class);
        $this->app->singleton(SiteComposer::class);
    }
}
