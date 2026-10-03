<?php

namespace Modules\Tenancy\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same role as {@see ResolvePublicTenant}, but for the spec §14 public website, which is
 * addressed by the tenant's numeric id rather than its slug: `/{tenant}/{slug}[/{page}]`. The
 * `{slug}` segment after the id is decorative — kept for a readable URL and never consulted here
 * — so a tenant renaming their business never breaks a link someone already has.
 *
 * Kept as its own class rather than a second mode on `ResolvePublicTenant` because the two public
 * surfaces key on different columns for different reasons: the §12 booking widget's JS only ever
 * knows the slug it was handed, while §14 wanted the id so the path would not change out from
 * under a shared link when the owner edits the slug.
 */
final class ResolvePublicTenantById
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Fails closed from here on, the same as ResolveTenant: an unresolved tenant means every
        // tenant-owned query returns nothing rather than everything (invariant #1).
        $this->context->enforce();

        $tenant = Tenant::query()->find((string) $request->route('tenant'));

        if ($tenant === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! $tenant->allowsAccess()) {
            // Suspended or cancelled (invariant #4 — nothing is deleted, access is simply
            // refused). A stranger gets the same not-found response a wrong id would, rather
            // than a message confirming the id belongs to a real, just-inactive business.
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
