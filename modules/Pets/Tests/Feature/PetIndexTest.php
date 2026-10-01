<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Models\Pet;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The §9 pet list, and §33's server-side pagination.
 *
 * Same expectations as the customer list, deliberately: nothing unbounded, nothing sorted by a
 * user-supplied column name, and a stable order so paging cannot hide a pet.
 */
final class PetIndexTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->named('Jane', 'Doe')->create();
    }

    public function test_the_list_is_paginated_server_side(): void
    {
        Pet::factory()->count(30)->of($this->customer->getKey())->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?per_page=5000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * An unvalidated sort column reaching orderBy is the one place Eloquent will interpolate user
     * input into SQL.
     */
    public function test_only_whitelisted_columns_can_be_sorted_on(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?sort=internal_notes')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    /**
     * Without a stable tiebreak two pets called Bella can swap places between pages, and one of
     * them is never seen.
     */
    public function test_paging_through_identical_names_shows_every_pet_exactly_once(): void
    {
        Pet::factory()->count(4)->of($this->customer->getKey())->named('Bella')->create();

        $seen = [];

        for ($page = 1; $page <= 4; $page++) {
            $seen[] = $this->actingAs($this->owner)
                ->getJson("/api/v1/pets?per_page=1&page={$page}")
                ->assertOk()
                ->json('data.0.id');
        }

        $this->assertCount(4, array_unique($seen));
    }

    public function test_pets_can_be_found_by_name_or_breed(): void
    {
        Pet::factory()->of($this->customer->getKey())->named('Bella')->create(['breed' => 'Cockapoo']);
        Pet::factory()->of($this->customer->getKey())->named('Max')->create(['breed' => 'Collie']);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?search=Bella')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bella');

        // Searching by breed, for when nobody can remember the name.
        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?search=Collie')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Max');
    }

    /**
     * Invariant #4: archived and deceased pets are hidden from the working list, never deleted.
     */
    public function test_inactive_pets_are_hidden_by_default_and_findable_on_request(): void
    {
        Pet::factory()->of($this->customer->getKey())->named('Bella')->create();
        Pet::factory()->of($this->customer->getKey())->named('Gone')->archived()->create();
        Pet::factory()->of($this->customer->getKey())->named('Rex')->deceased()->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bella');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?status[]=deceased')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rex');
    }

    public function test_pets_can_be_filtered_by_species_and_coat(): void
    {
        Pet::factory()->of($this->customer->getKey())->named('Bella')->create([
            'coat_type' => CoatType::Double,
        ]);
        Pet::factory()->of($this->customer->getKey())->named('Whiskers')->cat()->create([
            'coat_type' => CoatType::Short,
        ]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?species[]=cat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Whiskers');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?coat_type[]=double')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bella');
    }

    /**
     * The filter a groomer checking tomorrow's list actually wants.
     */
    public function test_pets_needing_handling_care_can_be_listed_on_their_own(): void
    {
        Pet::factory()->of($this->customer->getKey())->named('Bella')->needsHandlingCare()->create();
        Pet::factory()->of($this->customer->getKey())->named('Easy')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?needs_handling_care=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bella')
            ->assertJsonPath('data.0.needs_handling_care', true);
    }

    public function test_a_customers_pets_can_be_listed_on_their_own(): void
    {
        $other = Customer::factory()->named('Robert', 'Fletcher')->create();

        Pet::factory()->count(2)->of($this->customer->getKey())->create();
        Pet::factory()->of($other->getKey())->named('TheirDog')->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$this->customer->getKey()}/pets")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Each row shows whose pet it is, resolved through Crm's contract. Done naively that is a
     * query per row — an N+1 that `Model::shouldBeStrict()` cannot catch, because it is not an
     * Eloquent relationship. The index primes the directory with one query for the page.
     */
    public function test_the_list_resolves_every_owners_name_in_one_query(): void
    {
        $customers = Customer::factory()->count(10)->create();

        foreach ($customers as $customer) {
            Pet::factory()->of($customer->getKey())->create();
        }

        DB::enableQueryLog();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?per_page=25')
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $customerQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'from `customers`')
        );

        // One for the page of owners. Not ten.
        $this->assertLessThanOrEqual(2, count($customerQueries));
    }
}
