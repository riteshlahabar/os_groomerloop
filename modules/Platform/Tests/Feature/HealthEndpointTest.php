<?php

namespace Modules\Platform\Tests\Feature;

use Tests\TestCase;

/**
 * Proves the Phase 0 module mechanism end to end: a route file inside a module folder is
 * discovered, prefixed, named and served, and the security middleware is applied to it.
 */
final class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_reports_a_healthy_system(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok')
            ->assertJsonStructure(['data' => ['status', 'checks', 'time']]);
    }

    public function test_module_routes_are_registered_under_the_versioned_api_prefix(): void
    {
        $this->assertSame('/api/v1/health', route('api.v1.health', absolute: false));
    }

    public function test_security_headers_are_applied_to_api_responses(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertHeader('Content-Security-Policy');
    }
}
