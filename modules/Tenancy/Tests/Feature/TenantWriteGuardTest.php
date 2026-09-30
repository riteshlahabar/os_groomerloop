<?php

namespace Modules\Tenancy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Tenancy\Exceptions\TenantMismatch;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The global scope protects reads. These tests cover the writes, which it does not.
 *
 * Without them, a mass-assigned tenant_id from request input could insert a row into another
 * tenant's data — and because reads are scoped, the victim tenant would see the injected row
 * as their own while the attacker would never see it again. A read-side guard cannot detect
 * that, so it is enforced separately in BelongsToTenant.
 */
final class TenantWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->context = app(TenantContext::class);
    }

    public function test_tenant_id_is_filled_from_the_current_tenant(): void
    {
        $event = $this->context->runFor($this->tenantA, fn () => AuditEvent::create([
            'event' => 'thing.happened',
        ]));

        $this->assertSame($this->tenantA->getKey(), $event->tenant_id);
    }

    public function test_creating_a_record_for_another_tenant_is_refused(): void
    {
        $this->expectException(TenantMismatch::class);

        $this->context->runFor($this->tenantA, fn () => AuditEvent::create([
            'event' => 'injected.record',
            'tenant_id' => $this->tenantB->getKey(),
        ]));
    }

    public function test_a_records_tenant_cannot_be_reassigned(): void
    {
        $event = $this->context->runFor($this->tenantA, fn () => AuditEvent::create([
            'event' => 'thing.happened',
        ]));

        $this->expectException(TenantMismatch::class);

        $this->context->runFor($this->tenantA, function () use ($event): void {
            $event->tenant_id = $this->tenantB->getKey();
            $event->save();
        });
    }

    public function test_strict_mode_with_no_tenant_returns_nothing_rather_than_everything(): void
    {
        $this->context->runFor($this->tenantA, fn () => AuditEvent::create(['event' => 'a.one']));
        $this->context->runFor($this->tenantB, fn () => AuditEvent::create(['event' => 'b.one']));

        // This is the state a route reaches if its tenant middleware is missing or fails to
        // resolve. Failing closed here is the difference between an empty screen and a
        // cross-tenant data leak.
        $this->context->forget();
        $this->context->enforce();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_without_tenancy_is_the_only_way_to_query_across_tenants(): void
    {
        $this->context->runFor($this->tenantA, fn () => AuditEvent::create(['event' => 'a.one']));
        $this->context->runFor($this->tenantB, fn () => AuditEvent::create(['event' => 'b.one']));

        $total = $this->context->withoutTenancy(fn () => AuditEvent::query()->count());

        $this->assertSame(2, $total);
    }

    public function test_nested_tenant_contexts_restore_the_outer_tenant(): void
    {
        $this->context->runFor($this->tenantA, function (): void {
            $this->context->runFor($this->tenantB, function (): void {
                $this->assertSame($this->tenantB->getKey(), $this->context->id());
            });

            $this->assertSame(
                $this->tenantA->getKey(),
                $this->context->id(),
                'Leaving a nested tenant context must restore the one that surrounded it.'
            );
        });

        $this->assertFalse($this->context->hasTenant());
    }

    public function test_the_context_is_restored_even_when_the_callback_throws(): void
    {
        try {
            $this->context->runFor($this->tenantA, function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // Expected.
        }

        // A thrown exception must not leave the process acting as a tenant, or the next
        // request handled by this worker would inherit it.
        $this->assertFalse($this->context->hasTenant());
        $this->assertFalse($this->context->isStrict());
    }
}
