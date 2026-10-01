<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Pets\Models\Pet;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Spec §7 step 8 — "Add or import customers and pets" — now verified (`D-015`).
 *
 * The step needs both halves, which is why Pets owns the verifier rather than Crm. A business
 * holding four hundred imported customers and no pets cannot be booked at all, because a §11
 * appointment is for a pet; reporting that business as set up would have the §16 dashboard say
 * setup is finished while the thing setup exists to enable is still impossible.
 */
final class PetOnboardingStepTest extends TestCase
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

    public function test_the_step_is_outstanding_for_a_business_with_nothing_on_file(): void
    {
        $this->assertStep(completed: false, unavailable: false);
    }

    /**
     * The case the decision exists for.
     */
    public function test_customers_alone_do_not_complete_the_step(): void
    {
        Customer::factory()->count(5)->create();

        $this->assertStep(completed: false, unavailable: false);
    }

    public function test_pets_alone_do_not_complete_the_step(): void
    {
        $customer = Customer::factory()->create();
        Pet::factory()->of($customer->getKey())->create();

        // A pet cannot exist without a customer, so this is reached by archiving the customer
        // afterwards — a business that has tidied its only customer away has not set up a book.
        $customer->update(['status' => 'archived']);

        $this->assertStep(completed: false, unavailable: false);
    }

    public function test_both_halves_together_complete_the_step(): void
    {
        $customer = Customer::factory()->create();
        Pet::factory()->of($customer->getKey())->create();

        $this->assertStep(completed: true, unavailable: false);
    }

    /**
     * Now that the step is verified, Onboarding refuses to let the owner tick it by hand at all —
     * the behaviour it already had for services, hours and staff. Worth asserting here because
     * flipping `isVerified()` is what changed this endpoint's answer for this step (`D-015`): it
     * used to accept the claim, and a business could report itself set up with no pets on file.
     */
    public function test_the_step_can_no_longer_be_ticked_by_hand(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/customers_and_pets/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step');

        $this->assertStep(completed: false, unavailable: false);
    }

    public function test_archiving_the_only_pet_puts_the_step_back_to_outstanding(): void
    {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer->getKey())->create();

        $this->assertStep(completed: true, unavailable: false);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/pets/{$pet->getKey()}")
            ->assertNoContent();

        $this->assertStep(completed: false, unavailable: false);
    }

    /**
     * The step is now declared verified, which is what makes the verifier consulted at all — before
     * Pets existed it was ordinary client-marked progress (`D-015`).
     */
    public function test_the_step_is_declared_verified(): void
    {
        $this->assertTrue(OnboardingStep::CustomersAndPets->isVerified());

        // Still skippable: a brand-new business should be able to start taking bookings before it
        // has typed its old book in.
        $this->assertTrue(OnboardingStep::CustomersAndPets->isSkippable());
    }

    /**
     * Skipping still works on a verified step. It is a statement of intent ("not now"), not a claim
     * that the work is done, so it is recorded rather than derived.
     */
    public function test_the_step_can_still_be_skipped(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/onboarding/steps/customers_and_pets/skip')
            ->assertOk();

        $item = $this->stepPayload();

        $this->assertTrue($item['skipped']);
        $this->assertFalse($item['completed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPayload(): array
    {
        $checklist = $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->json('data.steps');

        foreach ($checklist as $item) {
            if ($item['step'] === OnboardingStep::CustomersAndPets->value) {
                return $item;
            }
        }

        $this->fail('The customers_and_pets step is missing from the checklist.');
    }

    private function assertStep(bool $completed, bool $unavailable): void
    {
        $item = $this->stepPayload();

        $this->assertSame($completed, $item['completed']);
        $this->assertSame($unavailable, $item['unavailable']);

        // Never "no module can answer this": Pets is built, so the step is answerable either way.
        $this->assertTrue($item['verified']);
    }
}
