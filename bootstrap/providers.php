<?php

use App\Providers\AppServiceProvider;
use App\Providers\RateLimitServiceProvider;
use Modules\Audit\AuditServiceProvider;
use Modules\Billing\BillingServiceProvider;
use Modules\Booking\BookingServiceProvider;
use Modules\Catalog\CatalogServiceProvider;
use Modules\Crm\CrmServiceProvider;
use Modules\Entitlements\EntitlementsServiceProvider;
use Modules\Identity\IdentityServiceProvider;
use Modules\Insights\InsightsServiceProvider;
use Modules\Notifications\NotificationsServiceProvider;
use Modules\Onboarding\OnboardingServiceProvider;
use Modules\Pets\PetsServiceProvider;
use Modules\Platform\PlatformServiceProvider;
use Modules\Scheduling\SchedulingServiceProvider;
use Modules\SuperAdmin\SuperAdminServiceProvider;
use Modules\Team\TeamServiceProvider;
use Modules\Tenancy\TenancyServiceProvider;
use Modules\Website\WebsiteServiceProvider;

return [
    // Shared kernel.
    AppServiceProvider::class,
    RateLimitServiceProvider::class,

    // Feature modules (D-007), in dependency order per spec §38.
    //
    // Listed explicitly rather than discovered by scanning modules/ at boot: a directory
    // scan on every cold request buys nothing and a module that is not listed here should
    // fail visibly rather than half-load.
    PlatformServiceProvider::class,

    // Tenancy must boot before anything that reads tenant-owned data, and Audit before
    // anything that records against it.
    TenancyServiceProvider::class,
    AuditServiceProvider::class,

    // Identity depends on both: it resolves tenants at login and audits role changes.
    IdentityServiceProvider::class,

    // Entitlements depends on Tenancy only. It must boot before any module that gates a route
    // on `entitlement:`, and deliberately before Billing — plan gating has to work for a
    // business that has never paid (spec §2, invariant #3).
    EntitlementsServiceProvider::class,

    // Billing depends on Entitlements (PlanRegistry), so it boots after it.
    BillingServiceProvider::class,

    // Onboarding depends on Tenancy and Audit only. Other modules register their own §7 step
    // verifiers into its registry as they are built, so it never reads their tables.
    OnboardingServiceProvider::class,

    // CRM depends on Entitlements (its routes are entitlement-gated) and is depended on by
    // Pets, Scheduling and Notifications through CustomerDirectory.
    CrmServiceProvider::class,

    // Pets must boot after Crm and after Onboarding: it registers itself into Crm's customer-merge
    // registry and Onboarding's §7 step-verifier registry, and both of those singletons are
    // declared by their own provider's register().
    PetsServiceProvider::class,

    // Catalog depends on Tenancy, Audit and Onboarding's verifier registry only. It boots before
    // Team, which owns the §10 "eligible groomers" link and validates service ids through this
    // module's contract (D-017).
    CatalogServiceProvider::class,

    // Team depends on Catalog's ServiceCatalog contract (D-017) and Onboarding's verifier
    // registry, so it boots after both. Scheduling (§11) and Booking (§12) will depend on this
    // module's StaffDirectory.
    TeamServiceProvider::class,

    // Scheduling (§11) depends on Catalog, Team, Crm and Pets' read contracts plus Onboarding's
    // verifier registry, so it boots last. Booking (§12) will depend on this module's
    // AppointmentScheduler (D-023) rather than building a second appointment engine.
    SchedulingServiceProvider::class,

    // Insights (§16) reports on Scheduling's AppointmentMetrics, Crm's CustomerMetrics, Catalog's
    // ServiceCatalog and Team's StaffDirectory, all read-only, so it boots after every one of
    // them — and after Entitlements, whose grade it checks before computing a single metric.
    InsightsServiceProvider::class,

    // Booking (§12) depends on Scheduling's AppointmentScheduler plus Catalog/Team/Crm/Pets'
    // read contracts, so it boots after all of them.
    BookingServiceProvider::class,

    // Notifications (§13) listens for Scheduling's domain events and reads Crm's consent and contact
    // details plus Catalog's service names, so it boots after all of them. Nothing depends on this
    // module in return — Scheduling books an appointment without knowing it exists, which is the
    // point of the event boundary (D-007). Registered 2026-10-02; before that the folder was on disk
    // with no provider at all and therefore dead code.
    NotificationsServiceProvider::class,

    // Website (§14) depends on Catalog's ServiceCatalog, Team's StaffDirectory and Onboarding's
    // BusinessProfileDirectory to render a tenant's public site, so it boots after all three. It
    // owns no service, staff or appointment data of its own and links every call to action to §12's
    // booking wizard rather than building a second one (D-023, D-030).
    WebsiteServiceProvider::class,

    // SuperAdmin (§31, first slice: D-026) depends on nothing but the framework — no other
    // module's contract — so its position here is arbitrary; listed last as the newest module.
    SuperAdminServiceProvider::class,
];
