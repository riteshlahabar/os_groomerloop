<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Modules\Tenancy\Http\Middleware\ResolveCustomerTenant;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenant;
use Modules\Tenancy\Http\Middleware\ResolvePublicTenantById;
use Modules\Tenancy\Http\Middleware\ResolveTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global: hardening headers belong on every response, including error responses
        // rendered before any route matches.
        $middleware->append(SecurityHeaders::class);

        // D-010: Sanctum SPA cookie authentication. The React frontend authenticates with
        // a same-origin session cookie and CSRF token rather than a bearer token held in
        // localStorage, so there is no token for an XSS payload to steal.
        $middleware->statefulApi();

        // Force JSON before anything can decide to redirect or render HTML.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Spec §33: every API route is rate limited. The limiter itself is defined in
        // RateLimitServiceProvider and keyed per user or per tenant, never globally.
        $middleware->throttleApi('api');

        // Tenant isolation depends on this ordering, so it is enforced globally rather than left
        // to each route to declare correctly.
        //
        // SubstituteBindings lives in the "api" group and therefore runs BEFORE any route
        // middleware — including ResolveTenant. That means route model binding would resolve
        // `{user}` or `{appointment}` with no tenant in context, the global scope would not
        // filter, and an owner of one business could load and modify a record belonging to
        // another. Prioritising ResolveTenant ahead of SubstituteBindings closes that hole for
        // every route in every module, present and future.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );

        // Same guarantee, for the spec §12 public booking routes: there is no authenticated user
        // to resolve a tenant from, so ResolvePublicTenant reads one from the route's {tenant}
        // slug instead — but it closes the identical D-014 hole if it does not also run ahead of
        // SubstituteBindings.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolvePublicTenant::class,
        );

        // Same guarantee again, for the spec §14 public website, which is keyed by tenant id
        // rather than slug (ResolvePublicTenantById).
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolvePublicTenantById::class,
        );

        // Same guarantee again, for the Customer Portal, also id-keyed.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveCustomerTenant::class,
        );

        // Laravel's default Authenticate middleware has no per-guard redirect — every
        // unauthenticated web request falls back to the single `login` route, which is staff's
        // own `/login` (Identity, `web` guard). Without this, an unauthenticated visitor to
        // `/portal/{tenant}/...` (Customer Portal, `customer` guard) was sent to the staff login
        // page instead of their own, where their credentials would not even be checked against
        // the right guard. Scoped to `/portal/*` so `/admin`/`/platform`'s own redirect is
        // untouched.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('portal/*')) {
                return route('customer-portal.login', ['tenant' => $request->route('tenant')]);
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
