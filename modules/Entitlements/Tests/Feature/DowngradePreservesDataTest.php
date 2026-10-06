<?php

namespace Modules\Entitlements\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Identity\Models\Invitation;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Invariant #4 and spec §35: "Downgrading a plan does not silently delete customer/pet/
 * appointment data."
 *
 * A downgrade must lock features and touch nothing else. This is the test that has to keep
 * being true for the life of the product, so it asserts the *count of every tenant-owned
 * table* before and after, discovered from the schema rather than listed by hand — a table
 * added in a later phase is covered the day its migration lands, without anyone remembering
 * to come back here.
 */
final class DowngradePreservesDataTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->tenant->plan_id = $this->planId('growth_partner');
        $this->tenant->save();
    }

    public function test_a_downgrade_locks_features_but_keeps_every_tenant_owned_row(): void
    {
        $this->givenTheBusinessHasData();

        $before = $this->tenantOwnedRowCounts();

        // Sanity: the fixture actually produced rows, or the comparison below is vacuous.
        $this->assertNotEmpty($before);
        $this->assertGreaterThan(0, array_sum($before));

        $this->assertTrue($this->entitlements()->allows(Feature::AiVoiceAgent));

        $this->downgradeTo('starter');

        // The feature is gone...
        $this->assertFalse($this->entitlements()->allows(Feature::AiVoiceAgent));
        $this->assertFalse($this->entitlements()->allows(Feature::LocalSeo));
        $this->assertFalse($this->entitlements()->allows(Feature::Automation));

        // ...while a feature Starter still carries is re-graded rather than removed. That is the
        // other half of invariant #4: dropping a tier lowers how much you get of something, it
        // does not erase it. Asserted on business_insights because automation — which used to
        // make this point — left Starter entirely on 2026-10-06 and now proves the case above.
        $this->assertSame(
            FeatureGrade::Basic,
            $this->entitlements()->gradeOf(Feature::BusinessInsights)
        );

        // ...and every row is still there.
        $this->assertSame(
            $before,
            $this->tenantOwnedRowCounts(),
            'A downgrade destroyed tenant-owned rows. That is invariant #4.'
        );

        // The one table that should have grown, did (invariant #8).
        $this->assertSame(1, $this->planChangeEventCount());
    }

    public function test_a_business_keeps_its_core_operating_system_after_any_downgrade(): void
    {
        $this->downgradeTo('starter');

        // Spec §25 ticks these for every tier. A downgrade that locked them would lock a
        // business out of its own customers and appointments — which invariant #4 exists to
        // prevent just as much as deletion does.
        foreach ([Feature::CoreOs, Feature::CrmPets, Feature::AppointmentsCalendar, Feature::OnlineBooking] as $feature) {
            $this->assertTrue(
                $this->entitlements()->allows($feature),
                "A downgraded business lost access to {$feature->value}."
            );
        }
    }

    public function test_a_downgrade_is_audited_with_both_plans_and_its_direction(): void
    {
        $this->downgradeTo('starter');

        $event = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => AuditEvent::query()->where('event', 'tenant.plan_changed')->latest('id')->firstOrFail()
        );

        $this->assertSame('growth_partner', $event->properties['from']);
        $this->assertSame('starter', $event->properties['to']);
        $this->assertSame('downgrade', $event->properties['direction']);
    }

    public function test_an_upgrade_is_recorded_as_an_upgrade(): void
    {
        $this->downgradeTo('starter');
        $this->downgradeTo('growth');

        $event = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => AuditEvent::query()->where('event', 'tenant.plan_changed')->latest('id')->firstOrFail()
        );

        $this->assertSame('upgrade', $event->properties['direction']);
    }

    public function test_reassigning_the_same_plan_changes_nothing_and_writes_no_event(): void
    {
        // Billing replays webhooks, so the same plan may be assigned twice. That must be a
        // no-op rather than a second audit entry implying the customer moved tiers.
        $this->downgradeTo('starter');
        $countAfterFirst = $this->planChangeEventCount();

        $this->downgradeTo('starter');

        $this->assertSame($countAfterFirst, $this->planChangeEventCount());
    }

    /**
     * Give the business something to lose: one row in every tenant-owned table that exists
     * at this phase. Later phases extend this as customers, pets and appointments arrive.
     */
    private function givenTheBusinessHasData(): void
    {
        app(TenantContext::class)->runFor($this->tenant, function (): void {
            User::factory()->memberOf($this->tenant, Role::Owner)->create();
            User::factory()->memberOf($this->tenant, Role::Groomer)->create();
            Invitation::factory()->create();
        });
    }

    /**
     * Row counts for every table carrying a tenant_id, for this tenant only.
     *
     * Discovered from the schema rather than hard-coded, so this guard grows with the product
     * instead of silently continuing to check three tables while twelve exist.
     *
     * @return array<string, int>
     */
    private function tenantOwnedRowCounts(): array
    {
        $counts = [];

        foreach ($this->tenantOwnedTables() as $table) {
            // The audit log is the one tenant-owned table that is *supposed* to grow during a
            // downgrade — recording the change is invariant #8. Freezing its count here would
            // make the test fail for the system working correctly, so it is asserted
            // separately below instead of being smuggled into the "nothing changed" snapshot.
            if ($table === 'audit_events') {
                continue;
            }

            $counts[$table] = \DB::table($table)
                ->where('tenant_id', $this->tenant->getKey())
                ->count();
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @return list<string>
     */
    private function tenantOwnedTables(): array
    {
        $tables = [];

        foreach (\Schema::getTableListing(schemaQualified: false) as $table) {
            if (\Schema::hasColumn($table, 'tenant_id')) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    private function downgradeTo(string $planKey): void
    {
        app(PlanRegistry::class)->assignToTenant($this->tenant, $this->planId($planKey));

        $this->tenant->refresh();
        app(TenantContext::class)->set($this->tenant);
        app(Entitlements::class)->flush();
    }

    private function entitlements(): Entitlements
    {
        app(TenantContext::class)->set($this->tenant);

        return app(Entitlements::class);
    }

    private function planId(string $key): int
    {
        return (int) Plan::query()->where('key', $key)->value('id');
    }

    private function planChangeEventCount(): int
    {
        return app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => AuditEvent::query()->where('event', 'tenant.plan_changed')->count()
        );
    }
}
