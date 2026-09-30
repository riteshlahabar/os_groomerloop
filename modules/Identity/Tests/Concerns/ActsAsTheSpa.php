<?php

namespace Modules\Identity\Tests\Concerns;

/**
 * Makes a test request look like one from the React SPA.
 *
 * D-010 authenticates with a session cookie, and Sanctum only puts session, cookie and CSRF
 * middleware in front of an API route once it has decided the request is first-party. It decides
 * that by matching the request's Origin or Referer header against SANCTUM_STATEFUL_DOMAINS.
 *
 * A real browser always sends Origin on these requests, so production is unaffected. A bare test
 * request sends neither, so without this the session is never started and any endpoint that calls
 * $request->session() fails with "Session store not set on request" — which is exactly what
 * login, registration and invitation acceptance all do.
 *
 * Call actAsTheSpa() from setUp(); withHeader() persists for every request in the test.
 */
trait ActsAsTheSpa
{
    protected function actAsTheSpa(): void
    {
        $this->withHeader('Origin', (string) config('app.frontend_url'));
    }
}
