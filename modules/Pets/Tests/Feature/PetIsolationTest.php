<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Models\Pet;
use Modules\Pets\Services\EloquentPetDirectory;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Invariant #1 for pets, proved through route model binding (D-014).
 *
 * Every assertion is 404, never 403: a 403 confirms the record exists, which tells one business
 * that another holds a pet with that id.
 *
 * There is a second boundary in this module that the CRM did not have. Pets are owned by a
 * customer *within* a tenant, so a caller can name a real customer of their own business and a
 * real pet id belonging to another family in that same business. The tenant scope offers no
 * protection there at all — see the ownership tests at the end.
 */
final class PetIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Customer $strangersCustomer;

    private Pet $strangersPet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $otherTenant = Tenant::factory()->create();

        [$this->strangersCustomer, $this->strangersPet] = app(TenantContext::class)->runFor(
            $otherTenant,
            function (): array {
                $customer = Customer::factory()->named('Someone', 'Else')->create();

                return [
                    $customer,
                    Pet::factory()->of($customer->getKey())->named('TheirDog')->create(),
                ];
            }
        );

        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->named('Jane', 'Doe')->create();
    }

    public function test_another_businesss_pet_cannot_be_read(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/pets/{$this->strangersPet->getKey()}")
            ->assertNotFound();
    }

    public function test_another_businesss_pet_cannot_be_updated(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$this->strangersPet->getKey()}", ['breed' => 'Collie'])
            ->assertNotFound();

        $this->assertNotSame('Collie', $this->strangersPet->refresh()->breed);
    }

    public function test_another_businesss_pet_cannot_be_archived(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/pets/{$this->strangersPet->getKey()}")
            ->assertNotFound();

        $this->assertSame('active', $this->strangersPet->refresh()->status->value);
    }

    public function test_another_businesss_pet_cannot_have_an_internal_note_written_on_it(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$this->strangersPet->getKey()}/internal-notes", [
                'internal_notes' => 'Written from the wrong business.',
            ])
            ->assertNotFound();

        $this->assertNull($this->strangersPet->refresh()->internal_notes);
    }

    public function test_the_pet_list_shows_only_this_businesss_pets(): void
    {
        $mine = Pet::factory()->of($this->customer->getKey())->named('Bella')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey());
    }

    public function test_search_cannot_find_another_businesss_pet(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/pets?search=TheirDog')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * The customer id comes from the URL rather than from a bound model, so the tenant scope on
     * pets is the only thing standing between a guessed id and another business's pet list.
     */
    public function test_another_businesss_customer_id_yields_an_empty_pet_list(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$this->strangersCustomer->getKey()}/pets")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * A pet cannot be created against another business's customer. The check runs through Crm's
     * tenant-scoped contract, so the answer is "no such customer" — a 422 that reveals nothing,
     * rather than an `exists:customers,id` rule that would have searched every tenant.
     */
    public function test_a_pet_cannot_be_attached_to_another_businesss_customer(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->strangersCustomer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseMissing('pets', ['name' => 'Bella']);
    }

    public function test_the_pet_directory_cannot_resolve_another_businesss_pet(): void
    {
        $directory = app(PetDirectory::class);

        $this->assertFalse($directory->exists($this->strangersPet->getKey()));
        $this->assertNull($directory->nameOf($this->strangersPet->getKey()));
        $this->assertFalse($directory->allowsOutreach($this->strangersPet->getKey()));
        $this->assertSame([], $directory->idsForCustomer($this->strangersCustomer->getKey()));
    }

    // --- Ownership inside one business ------------------------------------------------------

    /**
     * The check Scheduling and public Booking will both depend on.
     *
     * Two customers of the same salon are not isolated from each other by anything in Tenancy —
     * they are the same tenant. A booking request carries a customer id and a pet id, both from
     * the client, and without this nothing stops someone booking an appointment against another
     * family's dog.
     */
    public function test_the_directory_refuses_a_pet_that_belongs_to_a_different_customer(): void
    {
        $neighbour = Customer::factory()->named('Robert', 'Fletcher')->create();

        $mine = Pet::factory()->of($this->customer->getKey())->create();
        $theirs = Pet::factory()->of($neighbour->getKey())->create();

        $directory = app(PetDirectory::class);

        $this->assertTrue($directory->belongsTo($mine->getKey(), $this->customer->getKey()));
        $this->assertFalse($directory->belongsTo($theirs->getKey(), $this->customer->getKey()));
    }

    /**
     * Fails closed on an unknown pet, so a caller that does not check existence separately still
     * cannot act on a mismatch.
     */
    public function test_the_ownership_check_fails_closed_for_an_unknown_pet(): void
    {
        $directory = new EloquentPetDirectory;

        $this->assertFalse($directory->belongsTo(9_999_999, $this->customer->getKey()));
        $this->assertFalse($directory->belongsTo($this->strangersPet->getKey(), $this->strangersCustomer->getKey()));
    }
}
