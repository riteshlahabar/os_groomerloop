<?php

namespace Modules\Onboarding\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Contracts\OnboardingStepVerifier;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Onboarding\Models\BusinessProfile;
use Modules\Onboarding\Models\OnboardingProgress;
use Modules\Onboarding\Services\StepVerifiers;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The rules that stop the §7 checklist from being talked into lying.
 */
final class OnboardingStepDecisionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_an_acknowledgement_step_can_be_marked_done(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/policies/complete')
            ->assertOk()
            ->assertJsonPath('data.step', 'policies')
            ->assertJsonPath('data.completed', true);
    }

    /**
     * The core guard. Services, hours and staff are done when the records exist — letting
     * the client tick them would let a business declare itself ready to take bookings with
     * nothing to book.
     */
    public function test_a_verified_step_cannot_be_hand_ticked(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/services/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');
    }

    public function test_a_required_step_cannot_be_skipped(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/services/skip')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');
    }

    /**
     * Spec §3 lists solo and home-based groomers first among the target businesses, so
     * adding a second person must never be a precondition for operating.
     */
    public function test_a_solo_groomer_can_skip_adding_staff(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/staff/skip')
            ->assertOk()
            ->assertJsonPath('data.skipped', true);
    }

    public function test_an_unknown_step_is_not_found(): void
    {
        // The route parameter is typed as the enum, so this never reaches the action.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/buy_a_van/skip')
            ->assertNotFound();
    }

    public function test_completing_a_step_clears_an_earlier_skip_of_it(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/onboarding/steps/website/skip')->assertOk();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/website/complete')
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.skipped', false);

        // A step must never be both at once: the owner came back and did it, which is what
        // skipping was a promise to do.
        $progress = OnboardingProgress::query()->firstOrFail();
        $this->assertNotContains('website', $progress->skipped_steps);
    }

    public function test_step_decisions_are_audited(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/onboarding/steps/website/skip')->assertOk();

        $event = AuditEvent::query()->where('event', 'onboarding.step_skipped')->firstOrFail();

        $this->assertSame('website', $event->properties['step']);
    }

    public function test_onboarding_cannot_be_finished_while_a_required_step_is_undone(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/finish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('onboarding');
    }

    public function test_onboarding_can_be_finished_with_skipped_steps_outstanding(): void
    {
        $this->satisfyRequiredSteps();

        $this->actingAs($this->owner)->postJson('/api/v1/onboarding/steps/website/skip')->assertOk();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/finish')
            ->assertOk()
            ->assertJsonPath('data.is_finished', true);

        // That is what skippable means — the checklist keeps them, the owner is not blocked.
        $this->assertContains(
            'website',
            $this->actingAs($this->owner)->postJson('/api/v1/onboarding/finish')->json('data.skipped_steps')
        );
    }

    public function test_finishing_twice_keeps_the_original_timestamp(): void
    {
        $this->satisfyRequiredSteps();

        $first = $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/finish')->json('data.finished_at');

        $second = $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/finish')->json('data.finished_at');

        // Idempotent: a double-submitted button must not rewrite when the business went live.
        $this->assertSame($first, $second);
    }

    private function satisfyRequiredSteps(): void
    {
        BusinessProfile::factory()->create();

        $verifiers = app(StepVerifiers::class);

        foreach ([OnboardingStep::BusinessHours, OnboardingStep::Services] as $step) {
            $verifiers->register(new class($step) implements OnboardingStepVerifier
            {
                public function __construct(private readonly OnboardingStep $only) {}

                public function step(): OnboardingStep
                {
                    return $this->only;
                }

                public function isSatisfied(): bool
                {
                    return true;
                }
            });
        }
    }
}
