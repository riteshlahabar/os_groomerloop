<?php

namespace Modules\Entitlements\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Domain\FeatureGrade;
use Modules\Entitlements\Models\Plan;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The seeded catalog is the spec §25 matrix, cell for cell.
 *
 * This test names plans, which application code may never do (invariant #3). That is the
 * point of the exception PlanLiteralGuardTest carves out for test files: somebody has to
 * check that "Growth" grants what §25 says Growth grants, and only a test can.
 */
final class PlanMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_the_catalog_holds_the_four_plans_of_the_spec_at_their_listed_prices(): void
    {
        $this->assertSame(
            ['starter' => 7900, 'business' => 14900, 'growth' => 24900, 'growth_partner' => 39900],
            Plan::query()->ordered()->pluck('price_cents', 'key')->all()
        );
    }

    public function test_exactly_one_plan_is_the_default(): void
    {
        // More than one and PlanEntitlements' fallback becomes order-dependent; none and every
        // unsubscribed business loses access to the whole product.
        $this->assertSame(1, Plan::query()->where('is_default', true)->count());
        $this->assertSame('starter', Plan::query()->where('is_default', true)->value('key'));
    }

    /**
     * Every feature a plan grants, and the grade it must carry.
     *
     * Since `D-051` a grade is the granting tier's own name, so each plan's expectation is its
     * key list at one grade — Starter `basic`, Business `standard`, Growth `advanced`, Growth
     * Partner `enterprise`. Which keys sit in which tier is unchanged by that: 7 / 13 / 18 / 21.
     *
     * @return array<string, array{0: string, 1: array<string, FeatureGrade>}>
     */
    public static function planMatrix(): array
    {
        $core = [
            'core_os',
            'online_booking',
            'crm_pets',
            'appointments_calendar',
            'mobile_access',
            'basic_website',
        ];

        $growthKeys = [
            ...$core,
            'google_business_optimization',
            'review_support',
            'social_management',
            'customer_follow_up',
            'customer_retention',
            'local_seo',
            'content_marketing',
            'booking_conversion_optimization',
            'ai_business_tools',
            'automation',
            'business_insights',
            'growth_reporting',
        ];

        $at = static fn (array $keys, FeatureGrade $grade): array => array_fill_keys($keys, $grade);

        return [
            // No 'automation' row on either of the two lowest tiers: it left both on 2026-10-06,
            // because the price list only sells automation from Growth up.
            'starter' => ['starter', $at([...$core, 'business_insights'], FeatureGrade::Basic)],
            'business' => ['business', $at([
                ...$core,
                'google_business_optimization',
                'review_support',
                'social_management',
                'customer_follow_up',
                'customer_retention',
                'business_insights',
                'growth_reporting',
            ], FeatureGrade::Standard)],
            'growth' => ['growth', $at($growthKeys, FeatureGrade::Advanced)],
            'growth partner' => ['growth_partner', $at([
                ...$growthKeys,
                'ai_voice_agent',
                'monthly_growth_review',
                'dedicated_growth_support',
            ], FeatureGrade::Enterprise)],
        ];
    }

    /**
     * @param  array<string, FeatureGrade>  $expected
     */
    #[DataProvider('planMatrix')]
    public function test_a_plan_grants_exactly_the_features_the_spec_says(string $key, array $expected): void
    {
        $entitlements = $this->entitlementsFor($key);

        // Granted exactly these, at exactly these grades...
        $this->assertEquals($expected, $entitlements->all(), "Plan [{$key}] grants the wrong set of features.");

        // ...and nothing else. Asserting the complement matters: a feature wrongly added to a
        // cheap tier is revenue given away silently. Equality above would catch it, but
        // spelling it out per feature names the offender.
        foreach (Feature::all() as $feature) {
            $shouldHave = array_key_exists($feature->value, $expected);

            $this->assertSame(
                $shouldHave,
                $entitlements->allows($feature),
                sprintf(
                    'Plan [%s] should %sinclude %s.',
                    $key,
                    $shouldHave ? '' : 'not ',
                    $feature->value
                )
            );
        }
    }

    public function test_the_ai_voice_agent_is_the_growth_partner_exclusive_of_the_spec(): void
    {
        // Spec §25 gives AI voice agent to exactly one tier. Worth its own test because it is
        // the single most expensive capability to hand out by accident (§19).
        foreach (['starter', 'business', 'growth'] as $key) {
            $this->assertFalse(
                $this->entitlementsFor($key)->allows(Feature::AiVoiceAgent),
                "Plan [{$key}] must not include the AI voice agent."
            );
        }

        $this->assertTrue($this->entitlementsFor('growth_partner')->allows(Feature::AiVoiceAgent));
    }

    public function test_every_plan_includes_the_operating_system_itself(): void
    {
        $core = [
            Feature::CoreOs,
            Feature::OnlineBooking,
            Feature::CrmPets,
            Feature::AppointmentsCalendar,
            Feature::MobileAccess,
            Feature::BasicWebsite,
        ];

        foreach (['starter', 'business', 'growth', 'growth_partner'] as $key) {
            $entitlements = $this->entitlementsFor($key);

            foreach ($core as $feature) {
                $this->assertTrue(
                    $entitlements->allows($feature),
                    "Plan [{$key}] must include {$feature->value} — a business cannot be denied its own data."
                );
            }
        }
    }

    public function test_grades_are_ordered_so_a_route_can_demand_a_minimum(): void
    {
        // Asserted before moving to the next plan, deliberately. The entitlement service is a
        // container singleton, so two local variables are the same object — holding a
        // "starter" handle and a "partner" handle at once would give two names to one service
        // that answers for whichever tenant was set last.
        // BusinessInsights is the one graded feature every tier holds, so the whole ladder is
        // visible through it. Automation is the opposite case: absent below Growth (`D-042`),
        // so no minimum at all is satisfied there — `atLeast` on a feature a plan does not
        // grant must be false, never "at least the lowest grade".
        $starter = $this->entitlementsFor('starter');
        $this->assertTrue($starter->atLeast(Feature::BusinessInsights, FeatureGrade::Basic));
        $this->assertFalse($starter->atLeast(Feature::BusinessInsights, FeatureGrade::Standard));
        $this->assertFalse($starter->atLeast(Feature::Automation, FeatureGrade::Basic));

        $growth = $this->entitlementsFor('growth');
        $this->assertTrue($growth->atLeast(Feature::Automation, FeatureGrade::Advanced));
        $this->assertTrue($growth->atLeast(Feature::Automation, FeatureGrade::Basic));
        $this->assertFalse($growth->atLeast(Feature::Automation, FeatureGrade::Enterprise));

        $partner = $this->entitlementsFor('growth_partner');
        $this->assertTrue($partner->atLeast(Feature::Automation, FeatureGrade::Enterprise));
        $this->assertTrue($partner->atLeast(Feature::Automation, FeatureGrade::Advanced));
    }

    private function tenantOn(string $planKey): Tenant
    {
        $tenant = Tenant::factory()->create();
        $tenant->plan_id = Plan::query()->where('key', $planKey)->value('id');
        $tenant->save();

        return $tenant;
    }

    /**
     * Put the process on a plan and hand back the entitlement service.
     *
     * Uses TenantContext::set() rather than runFor(): runFor restores the previous context
     * when its callback returns, and the service re-resolves whenever the tenant's plan
     * pointer changes — so a helper that returned from inside runFor would hand back a
     * service that answers for the default plan, and every assertion after it would be
     * testing the wrong tier while looking like it passed.
     */
    private function entitlementsFor(string $planKey): Entitlements
    {
        app(TenantContext::class)->set($this->tenantOn($planKey));

        $entitlements = app(Entitlements::class);
        $entitlements->flush();

        return $entitlements;
    }
}
