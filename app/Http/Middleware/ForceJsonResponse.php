<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees the API answers in JSON, whatever the client asked for.
 *
 * Without this, a request that omits or mangles its Accept header gets Laravel's HTML
 * error page on a validation or authentication failure. That is both useless to the React
 * SPA and a disclosure risk, since the HTML debug page is far more revealing than a JSON
 * error body.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
