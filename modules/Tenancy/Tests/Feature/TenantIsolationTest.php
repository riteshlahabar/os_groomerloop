<?php

namespace Modules\Tenancy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Audit\Models\AuditEvent;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Modules\Tenancy\Tests\Fixtures\CountVisibleAuditEvents;
use Tests\TestCase;

/**
 * The Phase 1 gate for invariant #1.
 *
 * Tenant A must not reach Tenant B's data through any route out: a listing, a direct lookup,
 * a search, an export, or a background job. Each of those is a separate way to leak, so each
 * gets its own assertion rather than being covered by one representative case.
 *
 * AuditEvent is used as the subject because it is a real tenant-owned model created in this
 * phase. Using a real model rather than a synthetic fixture means the test exercises the same
 * trait, scope and migration that production code will.
 */
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private AuditEvent $eventOwnedByB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defineTenantScopedRoutes();

        $context = app(TenantContext::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'Alpha Grooming']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Beta Grooming']);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->getKey()]);

        // Two events for A, three for B — different totals, so a leak shows up as a wrong
        // count and not just as a wrong row.
        $context->runFor($this->tenantA, function (): void {
            AuditEvent::create(['event' => 'alpha.one']);
            AuditEvent::create(['event' => 'alpha.two']);
        });

        $this->eventOwnedByB = $context->runFor($this->tenantB, function (): AuditEvent {
            AuditEvent::create(['event' => 'beta.one']);
            AuditEvent::create(['event' => 'beta.two']);

            return AuditEvent::create(['event' => 'beta.three']);
        });
    }

    public function test_an_index_lists_only_the_current_tenants_records(): void
    {
        $response = $this->actingAs($this->userA)->getJson('/test-tenancy/audits');

        $response->assertOk()->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $row) {
            $this->assertSame($this->tenantA->getKey(), $row['tenant_id']);
        }
    }

    public function test_a_direct_lookup_of_another_tenants_record_is_not_found(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/test-tenancy/audits/'.$this->eventOwnedByB->getKey());

        // 404 and not 403: a "forbidden" would confirm the record exists, which leaks the very
        // thing the isolation is meant to hide.
        $response->assertNotFound();
    }

    /**
     * Route model binding is the sixth way out, and the easiest one to miss.
     *
     * SubstituteBindings lives in the "api" middleware group, so it runs before any route
     * middleware — including ResolveTenant. Left alone, `{audit}` would be resolved with no
     * tenant in context, the global scope would not filter, and one business could load another
     * business's record by guessing an id. bootstrap/app.php prioritises ResolveTenant ahead of
     * SubstituteBindings to prevent that; this test is the regression guard for that ordering.
     */
    public function test_route_model_binding_cannot_resolve_another_tenants_record(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/test-tenancy/bound-audits/'.$this->eventOwnedByB->getKey());

        $response->assertNotFound();
    }

    public function test_route_model_binding_still_resolves_the_current_tenants_record(): void
    {
        $own = AuditEvent::query()->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->getKey())
            ->firstOrFail();

        $this->actingAs($this->userA)
            ->getJson('/test-tenancy/bound-audits/'.$own->getKey())
            ->assertOk()
            ->assertJsonPath('id', $own->getKey());
    }

    public function test_a_search_cannot_reach_another_tenants_records(): void
    {
        $response = $this->actingAs($this->userA)
            ->getJson('/test-tenancy/audits/search?q=beta');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_export_contains_only_the_current_tenants_records(): void
    {
        $response = $this->actingAs($this->userA)->getJson('/test-tenancy/audits/export');

        $response->assertOk()->assertJsonCount(2, 'rows');
        $this->assertStringNotContainsString('beta.', (string) $response->getContent());
    }

    public function test_a_queued_job_sees_only_the_dispatching_tenants_records(): void
    {
        config(['queue.default' => 'database']);

        $context = app(TenantContext::class);

        // The job must be dispatched *inside* the closure body, not returned from it.
        // dispatch() hands back a PendingDispatch that only pushes the job when it is
        // destructed — returning it would push the job after runFor() had already restored
        // the previous (empty) tenant, and the payload would be stamped with no tenant.
        $context->runFor($this->tenantA, static function (): void {
            CountVisibleAuditEvents::dispatch();
        });

        // Simulate a worker in a separate process: nothing in memory, no request, no session.
        $context->forget();
        $context->relax();

        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

        $result = Cache::get(CountVisibleAuditEvents::CACHE_KEY);

        $this->assertNotNull($result, 'The queued job did not run.');
        $this->assertSame($this->tenantA->getKey(), $result['tenantId']);
        $this->assertTrue($result['strict'], 'A tenant-owned job must run in strict mode.');
        $this->assertSame(2, $result['count'], 'The job saw another tenant\'s records.');
    }

    public function test_the_worker_does_not_keep_the_tenant_after_the_job_finishes(): void
    {
        config(['queue.default' => 'database']);

        $context = app(TenantContext::class);
        $context->runFor($this->tenantA, static function (): void {
            CountVisibleAuditEvents::dispatch();
        });
        $context->forget();
        $context->relax();

        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

        // A worker that kept tenant A would hand it to the next job it picked up, which would
        // be a cross-tenant leak inside the queue itself.
        $this->assertFalse($context->hasTenant());
        $this->assertFalse($context->isStrict());
    }

    /**
     * Stand-ins for the endpoints later phases will build, defined here so the isolation
     * guarantee is tested now rather than after five modules already depend on it.
     */
    private function defineTenantScopedRoutes(): void
    {
        Route::middleware(['api', 'auth', 'tenant'])
            ->prefix('test-tenancy')
            ->group(function (): void {
                Route::get('audits', fn () => [
                    'data' => AuditEvent::query()->orderBy('id')->get()->toArray(),
                ]);

                // Declared before the wildcard route so they are not swallowed by it.
                Route::get('audits/search', fn (Request $request) => [
                    'data' => AuditEvent::query()
                        ->where('event', 'like', '%'.$request->query('q').'%')
                        ->get()
                        ->toArray(),
                ]);

                Route::get('audits/export', fn () => [
                    'rows' => AuditEvent::query()->pluck('event')->all(),
                ]);

                Route::get('audits/{audit}', fn (string $audit) => AuditEvent::query()->findOrFail($audit));

                // Resolved by Laravel's route model binding rather than by an explicit query, so
                // the binding path is covered and not just the hand-written one.
                Route::get('bound-audits/{audit}', fn (AuditEvent $audit) => $audit);
            });
    }
}
