<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Spec §7 step 4 — "Add groomers and their availability" — answered by Team's StaffVerifier.
 *
 * Unlike Services, this step is skippable: §3 lists solo/home-based groomers first, and forcing
 * a second person onto the checklist would lock out the segment the product is most obviously
 * for. It is still verified — completion is read from real records, not hand-ticked.
 */
final class StaffOnboardingStepTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_the_step_is_verified_but_skippable(): void
    {
        $this->assertTrue(OnboardingStep::Staff->isVerified());
        $this->assertTrue(OnboardingStep::Staff->isSkippable());

        $item = $this->step();
        $this->assertFalse($item['unavailable']);
        $this->assertFalse($item['completed']);
    }

    public function test_one_active_staff_member_completes_the_step(): void
    {
        StaffMember::factory()->create();

        $this->assertTrue($this->step()['completed']);
    }

    /**
     * A verified step ignores stored completion, so the only groomer leaving puts the step back
     * to outstanding.
     */
    public function test_the_only_staff_member_leaving_puts_the_step_back_to_outstanding(): void
    {
        $staff = StaffMember::factory()->create();
        $this->assertTrue($this->step()['completed']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/staff/{$staff->getKey()}")
            ->assertNoContent();

        $this->assertFalse($this->step()['completed']);
    }

    public function test_the_step_cannot_be_ticked_by_hand(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/staff/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');

        $this->assertFalse($this->step()['completed']);
    }

    /**
     * Skippable, so a business with no staff yet can still reach readiness without this step —
     * unlike Services, which gates `is_ready`.
     */
    public function test_being_skippable_it_does_not_block_readiness_on_its_own(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/staff/skip')
            ->assertOk();

        $this->assertTrue($this->step()['skipped']);
    }

    public function test_another_businesss_staff_do_not_complete_the_step(): void
    {
        $otherTenant = Tenant::factory()->create();

        app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => StaffMember::factory()->count(2)->create()
        );

        $this->assertFalse($this->step()['completed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function step(): array
    {
        $steps = $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->json('data.steps');

        foreach ($steps as $item) {
            if ($item['step'] === OnboardingStep::Staff->value) {
                return $item;
            }
        }

        $this->fail('The staff step is missing from the checklist.');
    }
}
