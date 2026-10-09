<?php

namespace Modules\Entitlements\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * Spec §35: "Plan restrictions are enforced server-side."
 *
 * The middleware is the enforcement point, so these tests go through real routes and real
 * HTTP rather than calling the service — a correct service behind a route that forgot to
 * declare its entitlement protects nothing.
 */
final class EntitlementMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        Route::middleware(['api', 'auth:sanctum', 'tenant'])->prefix('api/v1/testing')->group(function (): void {
            Route::get('voice', fn () => response()->json(['ok' => true]))
                ->middleware('entitlement:ai_voice_agent');

            Route::get('automation', fn () => response()->json(['ok' => true]))
                ->middleware('entitlement:automation');

            // The demanded grade is the top tier's (`D-051`: a grade is now the granting
            // tier's name), so Growth — the lowest tier holding automation at all — is the
            // below-minimum case and Growth Partner the satisfying one.
            Route::get('enterprise-automation', fn () => response()->json(['ok' => true]))
                ->middleware('entitlement:automation,enterprise');

            // A §25 core row, included in every tier. Gives the default-tier test below a
            // positive case that survives any repackaging of the optional features.
            Route::get('crm', fn () => response()->json(['ok' => true]))
                ->middleware('entitlement:crm_pets');

            Route::get('nonsense', fn () => response()->json(['ok' => true]))
                ->middleware('entitlement:not_a_real_feature');

            Route::get('owner-only-voice', fn () => response()->json(['ok' => true]))
                ->middleware(['permission:settings.manage', 'entitlement:ai_voice_agent']);
        });
    }

    public function test_a_plan_without_the_feature_is_refused(): void
    {
        $this->actingAs($this->userOn('growth'))
            ->getJson('/api/v1/testing/voice')
            ->assertStatus(402)
            ->assertJsonPath('feature', Feature::AiVoiceAgent->value);
    }

    public function test_a_plan_with_the_feature_is_allowed(): void
    {
        $this->actingAs($this->userOn('growth_partner'))
            ->getJson('/api/v1/testing/voice')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * 402 and 403 mean different things to the person on the other end, and the SPA has to
     * tell them apart: "ask your owner" versus "upgrade your plan". Collapsing both into 403
     * would have the product tell a groomer to buy something the business already has.
     */
    public function test_a_plan_refusal_is_402_and_a_permission_refusal_is_403(): void
    {
        // Both created before either request. A request leaves the resolved tenant in the
        // container singleton, and BelongsToTenant refuses to create a record for a different
        // tenant than the one currently in context — so building the second user after the
        // first request would throw TenantMismatch rather than test anything.
        $wrongRole = $this->userOn('growth_partner', Role::Groomer);
        $wrongPlan = $this->userOn('growth', Role::Owner);

        // Right plan, wrong role.
        $this->actingAs($wrongRole)
            ->getJson('/api/v1/testing/owner-only-voice')
            ->assertForbidden();

        // Right role, wrong plan.
        $this->actingAs($wrongPlan)
            ->getJson('/api/v1/testing/owner-only-voice')
            ->assertStatus(402);
    }

    public function test_a_grade_below_the_minimum_is_refused(): void
    {
        // Growth has automation, at `advanced`. It is the lowest tier that has the feature at
        // all since 2026-10-06 — on Starter or Business this would demonstrate "feature absent",
        // a different refusal already covered above, rather than "grade below minimum".
        $growthUser = $this->userOn('growth');

        $this->actingAs($growthUser)
            ->getJson('/api/v1/testing/automation')
            ->assertOk();

        $this->actingAs($growthUser)
            ->getJson('/api/v1/testing/enterprise-automation')
            ->assertStatus(402)
            ->assertJsonPath('required_grade', 'enterprise');
    }

    public function test_a_grade_at_or_above_the_minimum_is_allowed(): void
    {
        $this->actingAs($this->userOn('growth_partner'))
            ->getJson('/api/v1/testing/enterprise-automation')
            ->assertOk();
    }

    /**
     * A typo in a route definition must fail loudly at request time rather than quietly
     * denying — a silent 402 on a misspelled feature is a bug that looks like a billing
     * decision, and nobody would think to check the route file.
     */
    public function test_an_unknown_feature_in_a_route_is_a_programming_error(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);

        $this->actingAs($this->userOn('growth_partner'))
            ->getJson('/api/v1/testing/nonsense');
    }

    /**
     * Fails closed, for the same reason the tenant scope does (D-012). A business whose plan
     * cannot be determined gets nothing, rather than everything.
     */
    public function test_a_business_with_no_plan_and_no_default_is_entitled_to_nothing(): void
    {
        Plan::query()->update(['is_default' => false]);

        // No plan_id either — a tenant that still points at a real plan is entitled by that
        // plan, default or not, so clearing the default alone would prove nothing.
        $tenant = Tenant::factory()->create();
        $user = User::factory()->memberOf($tenant, Role::Owner)->create();

        $this->actingAs($user)->getJson('/api/v1/testing/voice')->assertStatus(402);

        // Not even the features every plan includes. There is no plan, so there is no grant.
        $this->actingAs($user)->getJson('/api/v1/testing/automation')->assertStatus(402);
    }

    /**
     * The registered-but-not-subscribed case: no plan_id, so the default tier applies.
     */
    public function test_a_business_with_no_plan_falls_back_to_the_default_tier(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->memberOf($tenant, Role::Owner)->create();

        $this->assertNull($tenant->plan_id);

        // Starter includes CRM + pets. A core §25 row is the right positive here: it is what
        // proves the fallback *resolved a plan* rather than answering with nothing, which is the
        // entire difference between this test and the no-default case above — and unlike an
        // optional feature it stays true through any later repackaging.
        $this->actingAs($user)->getJson('/api/v1/testing/crm')->assertOk();

        // ...and does not include the voice agent.
        $this->actingAs($user)->getJson('/api/v1/testing/voice')->assertStatus(402);

        // Nor automation, which left the Starter tier on 2026-10-06.
        $this->actingAs($user)->getJson('/api/v1/testing/automation')->assertStatus(402);
    }

    private function userOn(string $planKey, Role $role = Role::Owner): User
    {
        $tenant = Tenant::factory()->create();
        $tenant->plan_id = Plan::query()->where('key', $planKey)->value('id');
        $tenant->save();

        return User::factory()->memberOf($tenant, $role)->create();
    }
}
