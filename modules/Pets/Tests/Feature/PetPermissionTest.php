<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Models\Pet;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may do what to a pet record (spec §5, §9).
 */
final class PetPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();

        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create();
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatWorkWithAnimals(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
        ];
    }

    /**
     * A groomer who cannot look up whose dog is arriving, and what it is like to handle, cannot do
     * the job.
     */
    #[DataProvider('rolesThatWorkWithAnimals')]
    public function test_everyone_working_with_animals_can_read_pet_records(Role $role): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();
        $user = $this->userWith($role);

        $this->actingAs($user)->getJson('/api/v1/pets')->assertOk();
        $this->actingAs($user)->getJson("/api/v1/pets/{$pet->getKey()}")->assertOk();
        $this->actingAs($user)
            ->getJson("/api/v1/customers/{$this->customer->getKey()}/pets")
            ->assertOk();
    }

    /**
     * §5 scopes Marketing to growth modules. A campaign tool has no business holding a list of
     * customers' animals.
     */
    public function test_marketing_cannot_reach_pet_records(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();
        $user = $this->userWith(Role::Marketing);

        $this->actingAs($user)->getJson('/api/v1/pets')->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/pets/{$pet->getKey()}")->assertForbidden();
    }

    /**
     * The split that makes §9's note permission worth having: a groomer reads the record and
     * writes its handling history, but does not edit the record itself.
     */
    public function test_a_groomer_cannot_create_edit_or_archive_a_pet(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();
        $groomer = $this->userWith(Role::Groomer);

        $this->actingAs($groomer)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
            ])
            ->assertForbidden();

        $this->actingAs($groomer)
            ->putJson("/api/v1/pets/{$pet->getKey()}", ['breed' => 'Collie'])
            ->assertForbidden();

        $this->actingAs($groomer)
            ->deleteJson("/api/v1/pets/{$pet->getKey()}")
            ->assertForbidden();
    }

    public function test_the_front_desk_can_run_the_pet_records_day_to_day(): void
    {
        $user = $this->userWith(Role::FrontDesk);

        $this->actingAs($user)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
            ])
            ->assertCreated();

        $pet = Pet::query()->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/pets/{$pet->getKey()}", ['weight_lb' => 18.5])
            ->assertOk();
    }

    /**
     * Invariant #3, failing closed as D-012 requires: a business whose plan cannot be determined
     * gets nothing rather than everything, and a plan refusal is 402 — "upgrade" — not 403.
     */
    public function test_a_business_with_no_determinable_plan_is_refused_with_402(): void
    {
        Plan::query()->update(['is_default' => false]);

        $user = $this->ownerOfAnotherBusiness();

        $this->actingAs($user)->getJson('/api/v1/pets')->assertStatus(402);
    }

    public function test_the_default_plan_includes_pet_records(): void
    {
        // Spec §25 gives CRM + pets to every plan, so the gate refuses nobody today. It is
        // declared anyway because the matrix is data and packaging can change without a deploy.
        $this->actingAs($this->ownerOfAnotherBusiness())
            ->getJson('/api/v1/pets')
            ->assertOk();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }

    /**
     * BelongsToTenant refuses to create a record for a tenant other than the one in context — the
     * guard that makes invariant #1 hold for writes — so this is built inside runFor.
     */
    private function ownerOfAnotherBusiness(): User
    {
        $tenant = Tenant::factory()->create();

        return app(TenantContext::class)->runFor(
            $tenant,
            fn (): User => User::factory()->memberOf($tenant, Role::Owner)->create()
        );
    }
}
