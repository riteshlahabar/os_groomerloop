<?php

namespace Modules\Platform\Tests\Feature;

use Tests\TestCase;

/**
 * Guards the cross-cutting security posture of the API.
 *
 * These are regression tests for mistakes that are invisible in normal use: a wildcard CORS
 * origin works fine in development and silently exposes every authenticated endpoint to any
 * website, and a leaked framework header costs nothing until someone is choosing an exploit.
 */
final class ApiSecurityTest extends TestCase
{
    public function test_the_configured_spa_origin_is_allowed_with_credentials(): void
    {
        config()->set('cors.allowed_origins', ['http://localhost:5173']);

        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/v1/health');

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_an_unknown_origin_is_not_granted_access(): void
    {
        config()->set('cors.allowed_origins', [
            'http://localhost:5173',
            'http://localhost:8000',
        ]);

        $response = $this->withHeader('Origin', 'https://evil.example')
            ->getJson('/api/v1/health');

        // The security property is that the hostile origin is never echoed back: a browser
        // only releases the response when Access-Control-Allow-Origin equals the origin it
        // sent. Note that asserting the header is *absent* would be wrong — when exactly one
        // origin is configured, the CORS service emits it unconditionally as a static,
        // cacheable value, which is still safe because it cannot match the caller.
        $this->assertNotSame(
            'https://evil.example',
            $response->headers->get('Access-Control-Allow-Origin'),
            'An unlisted origin must never be echoed back as an allowed origin.'
        );

        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_is_never_configured_with_a_wildcard_origin(): void
    {
        // D-010 authenticates with cookies, and a wildcard origin is incompatible with
        // credentialed requests as well as being unsafe.
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertTrue(config('cors.supports_credentials'));
    }

    public function test_the_framework_and_server_are_not_advertised(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertFalse($response->headers->has('X-Powered-By'));
        $this->assertFalse($response->headers->has('Server'));
    }

    public function test_public_endpoints_are_rate_limited(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-RateLimit-Limit', '30');
    }
}
