<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Domain\Permission;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on a permission: `->middleware('permission:customers.manage')`.
 *
 * Route-level rather than per-method, so an endpoint cannot be added without declaring what it
 * requires — which is the point of keeping cross-cutting concerns in middleware. Policies still
 * handle per-record decisions; this handles "may this user reach this endpoint at all".
 *
 * Several permissions may be listed, and any one of them is enough:
 * `permission:appointments.manage,appointments.update_status`.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        foreach ($permissions as $permission) {
            $case = Permission::tryFrom($permission);

            // A typo in a route definition must not silently grant access, so an unknown
            // permission name is a programming error rather than a denial.
            if ($case === null) {
                throw new \InvalidArgumentException("Unknown permission [{$permission}].");
            }

            if ($user->hasPermission($case)) {
                return $next($request);
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'You do not have permission to do that.');
    }
}
