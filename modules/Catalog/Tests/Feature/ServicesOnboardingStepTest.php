<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Service;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Spec §7 step 5 — "Services, prices and durations" — now answerable.
 *
 * This step has read `unavailable` since Phase 4: Onboarding declared it verified, but no module
 * could answer it, so the checklist reported "coming soon" rather than nagging the owner about
 * something the product could not yet accept. Catalog closes that.
 *
 * It is also a **required** step, so it is what stands between a business and `is_ready` — nobody can
 * book a salon whose services are unknown.
 */
final class ServicesOnboardingStepTest extends TestCase
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

    /**
     * The regression that matters most in this file: before Catalog existed the step was
     * `unavailable`, and a client rendered it as "coming soon". It must now be an ordinary
     * outstanding task.
     */
    public function test_the_step_is_no_longer_unavailable(): void
    {
        $item = $this->step();

        $this->assertFalse($item['unavailable']);
        $this->assertFalse($item['completed']);
        $this->assertTrue($item['verified']);
    }

    public function test_one_active_service_completes_the_step(): void
    {
        Service::factory()->create();

        $this->assertTrue($this->step()['completed']);
    }

    /**
     * A verified step ignores stored completion, so retiring the menu puts it back to outstanding —
     * the honest answer, and the one the §16 dashboard alert needs.
     */
    public function test_retiring_every_service_puts_the_step_back_to_outstanding(): void
    {
        $service = Service::factory()->create();

        $this->assertTrue($this->step()['completed']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/services/{$service->getKey()}")
            ->assertNoContent();

        $this->assertFalse($this->step()['completed']);
    }

    /**
     * An add-on on its own is still a sellable service — a salon that only offers nail trims sells
     * nail trims — so it satisfies the step. The distinction that matters for §12 is whether the
     * public may book it, which is a different question.
     */
    public function test_an_add_on_counts_as_a_service_for_the_checklist(): void
    {
        Service::factory()->addOn()->create();

        $this->assertTrue($this->step()['completed']);
    }

    /**
     * A service the salon does not publish online still counts: the step is about having a price
     * list, not about being publicly bookable.
     */
    public function test_a_service_not_offered_online_still_counts(): void
    {
        Service::factory()->notBookableOnline()->create();

        $this->assertTrue($this->step()['completed']);
    }

    /**
     * Required, not skippable: nobody can book a salon whose services are unknown, so this step is
     * part of what `is_ready` means.
     */
    public function test_the_step_is_required_and_gates_readiness(): void
    {
        $this->assertFalse(OnboardingStep::Services->isSkippable());

        $readyWithout = $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->json('data.is_ready');

        $this->assertFalse($readyWithout);
    }

    public function test_the_step_cannot_be_ticked_by_hand(): void
    {
        // Verified steps complete themselves when the work is done; a checklist that could be ticked
        // without doing the work would report a business as ready to take bookings with no menu.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/services/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');

        $this->assertFalse($this->step()['completed']);
    }

    /**
     * Another business's menu must not satisfy this business's checklist.
     */
    public function test_another_businesss_services_do_not_complete_the_step(): void
    {
        $otherTenant = Tenant::factory()->create();

        app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => Service::factory()->count(3)->create()
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
            if ($item['step'] === OnboardingStep::Services->value) {
                return $item;
            }
        }

        $this->fail('The services step is missing from the checklist.');
    }
}
