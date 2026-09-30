<?php

namespace Modules\Entitlements\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

final class PlanEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_the_price_list_is_public(): void
    {
        // The marketing site's pricing page reads this before anyone has an account.
        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.price_cents', 7900)
            ->assertJsonPath('data.3.price_cents', 39900);
    }

    public function test_the_price_list_reports_every_feature_including_the_excluded_ones(): void
    {
        $response = $this->getJson('/api/v1/plans')->assertOk();

        // All 21 §25 rows on every plan, so the comparison grid can be rendered from one
        // response rather than the client inferring which cells are "--".
        $this->assertCount(count(Feature::all()), $response->json('data.0.features'));

        $starterVoice = collect($response->json('data.0.features'))
            ->firstWhere('key', Feature::AiVoiceAgent->value);

        $this->assertFalse($starterVoice['included']);
        $this->assertNull($starterVoice['grade']);
    }

    public function test_a_retired_plan_is_not_offered_to_new_customers(): void
    {
        Plan::query()->where('key', 'business')->update(['is_active' => false]);

        $keys = collect($this->getJson('/api/v1/plans')->json('data'))->pluck('key');

        $this->assertNotContains('business', $keys);
        $this->assertCount(3, $keys);
    }

    public function test_entitlements_require_authentication(): void
    {
        $this->getJson('/api/v1/entitlements')->assertUnauthorized();
    }

    public function test_a_business_can_read_what_its_plan_includes(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->plan_id = Plan::query()->where('key', 'growth')->value('id');
        $tenant->save();

        $user = User::factory()->memberOf($tenant, Role::Groomer)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/entitlements')->assertOk();

        $this->assertSame('growth', $response->json('data.plan.key'));

        $features = collect($response->json('data.features'))->keyBy('key');

        $this->assertTrue($features[Feature::AiBusinessTools->value]['included']);
        $this->assertSame('advanced', $features[Feature::BusinessInsights->value]['grade']);
        $this->assertFalse($features[Feature::AiVoiceAgent->value]['included']);
    }

    /**
     * Entitlement is a property of the business, not of the person: a groomer and an owner in
     * the same salon are entitled to the same features and differ only by permission. Getting
     * this wrong would have the product sell plans per seat by accident.
     */
    public function test_every_role_in_a_business_sees_the_same_entitlements(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->plan_id = Plan::query()->where('key', 'growth')->value('id');
        $tenant->save();

        $owner = User::factory()->memberOf($tenant, Role::Owner)->create();
        $groomer = User::factory()->memberOf($tenant, Role::Groomer)->create();

        $asOwner = $this->actingAs($owner)->getJson('/api/v1/entitlements')->json('data.features');
        $asGroomer = $this->actingAs($groomer)->getJson('/api/v1/entitlements')->json('data.features');

        $this->assertSame($asOwner, $asGroomer);
    }
}
