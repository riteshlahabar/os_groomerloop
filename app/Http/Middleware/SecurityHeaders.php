<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies hardening headers to every response and strips server fingerprinting.
 *
 * Kept as middleware rather than per-controller code so no endpoint can be added without
 * it, which is the point of the owner's "use middleware highly" requirement.
 */
class SecurityHeaders
{
    /**
     * Headers applied to every response, API and web alike.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        // Never let a browser second-guess our declared content type.
        'X-Content-Type-Options' => 'nosniff',

        // This application is never framed. The embeddable booking widget of spec §12 is a
        // separate, deliberately-framed surface and will relax this for its own routes only.
        'X-Frame-Options' => 'DENY',

        // Do not leak tenant-identifying URLs to third parties via the Referer header — but
        // `no-referrer` also strips it on same-origin requests, which is what Sanctum's SPA
        // cookie auth (D-010) reads to decide a request is first-party at all
        // (EnsureFrontendRequestsAreStateful::fromFrontend() checks Referer, falling back to
        // Origin — and a plain same-origin fetch() sends no Origin header either). With
        // `no-referrer` every API call this application's own Blade pages make gets a clean
        // 401, masked until now because every automated test uses ActsAsTheSpa, which sets
        // these headers itself rather than relying on a real browser (see `D-027`).
        // `same-origin` keeps the original guarantee — a cross-origin request still gets no
        // Referer at all — while allowing the one legitimate same-origin use.
        'Referrer-Policy' => 'same-origin',

        'X-Permitted-Cross-Domain-Policies' => 'none',

        // Deny device access nothing in the OS asks for.
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=(), usb=()',
    ];

    /**
     * Headers whose presence tells an attacker what to attack.
     *
     * @var list<string>
     */
    private const REMOVED = ['X-Powered-By', 'Server'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        foreach (self::REMOVED as $header) {
            $response->headers->remove($header);

            // PHP adds X-Powered-By at the SAPI level, below Laravel's response object, so
            // removing it from $response alone leaves it on the wire. expose_php=Off in
            // php.ini is the real fix; this is the belt-and-braces one that travels with
            // the application to a server whose ini we do not control.
            if (! headers_sent()) {
                header_remove($header);
            }
        }

        // A JSON API loads no scripts, styles, images or fonts of its own, so the strictest
        // possible policy is also the correct one.
        if ($request->is('api/*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
            );
        }

        // Only asserted over HTTPS — sending it over plain HTTP is meaningless and would
        // lock out local development.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
