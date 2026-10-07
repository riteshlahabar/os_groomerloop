<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant resolution for the Customer Portal, addressed `/{tenant}/...` like the §14 public
 * website (id-keyed, per D-036) — unlike ResolveTenant, the tenant cannot be read purely from
 * the authenticated actor, because the portal's own login endpoint has no actor yet and still
 * needs to know which tenant's `Customer` row to check credentials against.
 *
 * So the tenant always comes from the URL, exactly like ResolvePublicTenantById. The one thing
 * this adds: if the `customer` guard already has an authenticated customer, their own
 * `tenant_id` must match the URL's tenant, or the request is refused outright. Without that
 * check, a customer who is legitimately logged in for Tenant A but follows or guesses a Tenant
 * B portal link would have TenantContext silently set to Tenant B while every tenant-scoped
 * query still runs as "the current customer" — reading or acting on the wrong business's data
 * under invariant #1, not merely a confusing URL.
 */
final class ResolveCustomerTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Same ordering as ResolveTenant: resolve the (possibly absent) actor before strict
        // mode is on, since that resolution is itself a tenant-owned query with no tenant set.
        $customer = $this->context->withoutTenancy(fn () => $request->user('customer'));

        $this->context->enforce();

        $tenant = Tenant::query()->find((string) $request->route('tenant'));

        if ($tenant === null || ! $tenant->allowsAccess()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($customer !== null && (int) $customer->tenant_id !== $tenant->getKey()) {
            abort(Response::HTTP_FORBIDDEN, 'This account does not belong to this business.');
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
