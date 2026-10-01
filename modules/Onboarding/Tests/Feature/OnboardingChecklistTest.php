<?php

namespace Modules\Onboarding\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Models\BusinessProfile;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The spec §7 checklist: what it reports, and why it cannot be talked into lying.
 */
final class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        // Needed so factories that do not set tenant_id themselves can create records
        // before the first request resolves a tenant. ResolveTenant overwrites this per
        // request, so it does not mask anything the endpoints do.
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_the_checklist_lists_every_step_of_the_spec_in_order(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/v1/onboarding')->assertOk();

        $steps = $response->json('data.steps');

        $this->assertCount(count(OnboardingStep::all()), $steps);
        $this->assertSame(OnboardingStep::values(), array_column($steps, 'step'));
        $this->assertSame(range(1, count($steps)), array_column($steps, 'position'));
    }

    public function test_the_account_step_is_already_done_for_a_registered_business(): void
    {
        $account = $this->stepFromApi(OnboardingStep::Account);

        $this->assertTrue($account['completed']);
        $this->assertTrue($account['verified']);
        $this->assertFalse($account['skippable']);
    }

    /**
     * The honest-reporting case: business hours have no verifier because Scheduling (§11) is not
     * built, and the checklist says so rather than nagging the owner to do something the product
     * cannot yet accept.
     *
     * This test used to use the Services step. It moved to BusinessHours when Catalog shipped and
     * registered a verifier for services — which is the mechanism working, not the test rotting.
     * Staff is the other one still unanswered, until Team (§23) arrives.
     */
    public function test_a_step_whose_module_does_not_exist_reports_as_unavailable(): void
    {
        $hours = $this->stepFromApi(OnboardingStep::BusinessHours);

        $this->assertTrue($hours['unavailable']);
        $this->assertFalse($hours['completed']);
        $this->assertTrue($hours['verified']);
    }

    /**
     * The counterpart, and the reason the distinction exists at all: a step whose module *is* built
     * reports as an ordinary outstanding task rather than as "coming soon".
     */
    public function test_a_step_whose_module_exists_is_outstanding_rather_than_unavailable(): void
    {
        $services = $this->stepFromApi(OnboardingStep::Services);

        $this->assertFalse($services['unavailable']);
        $this->assertFalse($services['completed']);
        $this->assertTrue($services['verified']);
    }

    public function test_business_details_completes_itself_once_the_profile_is_sufficient(): void
    {
        $this->assertFalse($this->stepFromApi(OnboardingStep::BusinessDetails)['completed']);

        $this->actingAs($this->owner)->putJson('/api/v1/business-profile', [
            'contact_email' => 'owner@happypaws.test',
            'city' => 'Austin',
        ])->assertOk();

        $this->assertTrue($this->stepFromApi(OnboardingStep::BusinessDetails)['completed']);
    }

    /**
     * A verified step tracks reality, in both directions. Deleting the underlying records
     * must put the step back to outstanding — otherwise the §16 dashboard would report a
     * business as set up after it had dismantled the setup.
     */
    public function test_a_verified_step_goes_back_to_outstanding_when_the_work_is_undone(): void
    {
        $this->actingAs($this->owner)->putJson('/api/v1/business-profile', [
            'contact_email' => 'owner@happypaws.test',
            'city' => 'Austin',
        ])->assertOk();

        $this->assertTrue($this->stepFromApi(OnboardingStep::BusinessDetails)['completed']);

        BusinessProfile::query()->delete();

        $this->assertFalse($this->stepFromApi(OnboardingStep::BusinessDetails)['completed']);
    }

    public function test_progress_counts_skipped_steps_as_dealt_with(): void
    {
        $before = $this->actingAs($this->owner)->getJson('/api/v1/onboarding')->json('data.percent_complete');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/website/skip')
            ->assertOk();

        $after = $this->actingAs($this->owner)->getJson('/api/v1/onboarding')->json('data.percent_complete');

        // A progress bar stuck because the owner does not want a website is a progress bar
        // nobody trusts.
        $this->assertGreaterThan($before, $after);
    }

    public function test_a_business_is_not_ready_while_a_required_step_is_outstanding(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.is_ready', false)
            ->assertJsonPath('data.is_finished', false);
    }

    public function test_a_business_is_ready_once_every_required_step_is_satisfied(): void
    {
        $this->satisfyEveryRequiredStep();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->assertJsonPath('data.is_ready', true);
    }

    /**
     * Registers stand-in verifiers for the steps whose modules are not built yet, so the
     * "ready" path can be tested today. Replaced by the real verifiers as Catalog,
     * Scheduling and Team land.
     */
    private function satisfyEveryRequiredStep(): void
    {
        BusinessProfile::factory()->create();

        $verifiers = app(StepVerifiers::class);

        foreach ([OnboardingStep::BusinessHours, OnboardingStep::Services] as $step) {
            $verifiers->register(new class($step) implements OnboardingStepVerifier
            {
                public function __construct(private readonly OnboardingStep $step) {}

                public function step(): OnboardingStep
                {
                    return $this->step;
                }

                public function isSatisfied(): bool
                {
                    return true;
                }
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stepFromApi(OnboardingStep $step): array
    {
        $steps = $this->actingAs($this->owner)->getJson('/api/v1/onboarding')->json('data.steps');

        return collect($steps)->firstWhere('step', $step->value);
    }
}
