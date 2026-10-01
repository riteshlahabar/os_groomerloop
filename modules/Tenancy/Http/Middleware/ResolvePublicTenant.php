<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes which tenant an unauthenticated public request is for, from a `{tenant}` route
 * segment bound to `Tenant::slug` — the public §12 booking widget's only way to say which salon
 * it is booking into, since there is no logged-in user to read a tenant off of the way
 * `ResolveTenant` does.
 *
 * `Tenant` itself is not tenant-owned, so resolving it by route-bound slug carries none of
 * D-014's hazard. Everything a public route touches *after* this middleware — services, staff,
 * availability, the appointment it creates — is tenant-owned, so this still needs the same
 * priority-list placement ahead of `SubstituteBindings` that `ResolveTenant` has (see
 * `bootstrap/app.php`), in case a future public route ever binds one of those by route parameter
 * rather than validating it through a contract the way `BookAppointment` already does.
 */
final class ResolvePublicTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Fails closed from here on, the same as ResolveTenant: an unresolved tenant means every
        // tenant-owned query returns nothing rather than everything (invariant #1).
        $this->context->enforce();

        $tenant = Tenant::query()->where('slug', (string) $request->route('tenant'))->first();

        if ($tenant === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $tenant->allowsAccess()) {
            // Suspended or cancelled (invariant #4 — nothing is deleted, access is simply
            // refused). A stranger gets the same not-found response a wrong slug would, rather
            // than a message confirming the slug belongs to a real, just-inactive business.
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
