<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes which tenant a request is acting for, and refuses the request if it cannot.
 *
 * Applied to every protected route group. It switches strict mode on before doing anything
 * else, so that even a request it cannot resolve fails closed: tenant-owned queries return
 * nothing rather than everything (invariant #1).
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Resolving the authenticated user is itself a query against users, which is a
        // tenant-owned table — and it necessarily happens before the tenant is known. So it
        // runs outside tenancy on purpose. Enforcing strict mode first would scope the lookup
        // to a tenant that has not been resolved yet, find no user, and reject every
        // authenticated request.
        $user = $this->context->withoutTenancy(fn () => $request->user());

        // From here on the request fails closed: tenant-owned queries return nothing unless a
        // tenant is established below.
        $this->context->enforce();

        if ($user === null) {
            // Unauthenticated. Authentication middleware will reject this; strict mode keeps
            // the request harmless in the meantime.
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            // A user with no tenant is a platform user (the GroomerLoop Admin role of spec §5).
            // They have their own route group and must not fall through to tenant routes.
            abort(Response::HTTP_FORBIDDEN, 'This account is not associated with a business.');
        }

        if (! $tenant->allowsAccess()) {
            // Suspended or cancelled. Nothing is deleted (invariant #4) — access is simply
            // refused until the account is reinstated.
            abort(Response::HTTP_FORBIDDEN, 'This business account is not active.');
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
