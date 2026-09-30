<?php

namespace Modules\Audit\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Audit\Exceptions\AuditEventIsImmutable;
use Modules\Audit\Models\AuditEvent;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Covers invariant #8: critical actions write audit events, and those events stay trustworthy.
 */
final class AuditRecorderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->context = app(TenantContext::class);
    }

    public function test_the_recorder_is_resolved_from_its_contract(): void
    {
        // Other modules depend on the interface, never the implementation (D-007).
        $this->assertInstanceOf(AuditRecorder::class, app(AuditRecorder::class));
    }

    public function test_an_event_is_recorded_against_the_current_tenant(): void
    {
        $event = $this->context->runFor(
            $this->tenant,
            fn () => app(AuditRecorder::class)->record('tenant.created')
        );

        $this->assertSame($this->tenant->getKey(), $event->tenant_id);
        $this->assertSame('tenant.created', $event->event);
        $this->assertNotNull($event->created_at);
    }

    public function test_an_event_records_the_acting_user_and_the_subject(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->getKey()]);

        $event = $this->context->runFor($this->tenant, function () use ($user) {
            $this->actingAs($user);

            return app(AuditRecorder::class)->record(
                'user.role_changed',
                $user,
                ['from' => 'front_desk', 'to' => 'manager']
            );
        });

        $this->assertSame($user->getKey(), $event->user_id);
        $this->assertSame($user->getMorphClass(), $event->auditable_type);
        $this->assertSame($user->getKey(), $event->auditable_id);
        $this->assertSame(['from' => 'front_desk', 'to' => 'manager'], $event->properties);
    }

    public function test_an_event_can_belong_to_no_tenant(): void
    {
        // Platform-level actions — a GroomerLoop Admin operating outside any one business.
        $event = app(AuditRecorder::class)->record('platform.maintenance_started');

        $this->assertNull($event->tenant_id);
    }

    public function test_a_platform_event_is_not_visible_to_a_tenant(): void
    {
        app(AuditRecorder::class)->record('platform.maintenance_started');

        $visible = $this->context->runFor($this->tenant, fn () => AuditEvent::query()->count());

        $this->assertSame(0, $visible);
    }

    public function test_a_recorded_event_cannot_be_modified(): void
    {
        $event = $this->context->runFor(
            $this->tenant,
            fn () => app(AuditRecorder::class)->record('appointment.cancelled')
        );

        $this->expectException(AuditEventIsImmutable::class);

        $event->update(['event' => 'appointment.completed']);
    }

    public function test_a_recorded_event_cannot_be_deleted(): void
    {
        $event = $this->context->runFor(
            $this->tenant,
            fn () => app(AuditRecorder::class)->record('appointment.cancelled')
        );

        $this->expectException(AuditEventIsImmutable::class);

        $event->delete();
    }

    public function test_an_oversized_user_agent_is_truncated_to_fit_the_column(): void
    {
        $event = $this->context->runFor($this->tenant, function () {
            request()->headers->set('User-Agent', str_repeat('x', 900));

            return app(AuditRecorder::class)->record('session.started');
        });

        $this->assertSame(512, mb_strlen((string) $event->user_agent));
    }
}
