<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Domain\PetStatus;
use Modules\Pets\Models\Pet;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Pet records over HTTP (spec §9).
 */
final class PetEndpointTest extends TestCase
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

    public function test_a_pet_can_be_created_with_a_name_and_a_species(): void
    {
        // Spec §9: a front desk taking a booking over the phone has those two and often nothing
        // else. Demanding a breed or a birthday would have staff typing guesses.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Bella')
            ->assertJsonPath('data.species', 'dog')
            ->assertJsonPath('data.sex', 'unknown')
            ->assertJsonPath('data.status', PetStatus::Active->value)
            // Resolved through Crm's contract, so a list or a calendar never needs a second call.
            ->assertJsonPath('data.customer_name', 'Jane Doe');

        $this->assertDatabaseHas('pets', [
            'name' => 'Bella',
            'customer_id' => $this->customer->getKey(),
            'tenant_id' => $this->tenant->getKey(),
        ]);
    }

    public function test_one_customer_can_have_several_pets(): void
    {
        // Spec §9: "multiple pets linked to one customer".
        Pet::factory()->of($this->customer->getKey())->named('Bella')->create();
        Pet::factory()->of($this->customer->getKey())->named('Max')->cat()->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$this->customer->getKey()}/pets")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_name_and_species_are_required(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', ['customer_id' => $this->customer->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'species']);
    }

    /**
     * §10 will price and time services by species, and §16 reports on the mix. Free text would
     * give one species five spellings.
     */
    public function test_an_unknown_species_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Kevin',
                'species' => 'iguana',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('species');
    }

    public function test_a_pet_must_belong_to_a_customer_that_exists(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => 9_999_999,
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    /**
     * A rescue arrives with a known approximate age and no papers. Forcing a made-up birthday to
     * record "about seven" would put a false date on the record.
     */
    public function test_an_approximate_age_is_accepted_when_there_is_no_birthday(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
                'approximate_age_years' => 7,
            ])
            ->assertCreated()
            ->assertJsonPath('data.age_years', 7)
            ->assertJsonPath('data.age_is_approximate', true)
            ->assertJsonPath('data.date_of_birth', null);
    }

    public function test_a_date_of_birth_gives_an_exact_age(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
                'date_of_birth' => now()->subYears(3)->subMonths(2)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.age_years', 3)
            ->assertJsonPath('data.age_is_approximate', false);
    }

    /**
     * Recording both is a contradiction rather than extra detail, and whichever the UI then showed
     * would be a coin toss.
     */
    public function test_a_birthday_and_an_approximate_age_cannot_both_be_given(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/pets', [
                'customer_id' => $this->customer->getKey(),
                'name' => 'Bella',
                'species' => PetSpecies::Dog->value,
                'date_of_birth' => '2020-03-14',
                'approximate_age_years' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('approximate_age_years');
    }

    /**
     * One fact stored two ways, so setting either must clear the other — otherwise correcting
     * "about 7" to a real birthday leaves the record internally inconsistent.
     */
    public function test_supplying_a_birthday_clears_a_previously_approximate_age(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->aboutYearsOld(7)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$pet->getKey()}", ['date_of_birth' => '2021-06-01'])
            ->assertOk()
            ->assertJsonPath('data.age_is_approximate', false);

        $this->assertNull($pet->refresh()->approximate_age_years);
    }

    public function test_a_pet_can_be_read_back_with_its_handling_details(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->needsHandlingCare()->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/pets/{$pet->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.needs_handling_care', true)
            ->assertJsonPath('data.special_instructions', 'Muzzle for nail trims.');
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->named('Bella')->create([
            'breed' => 'Cockapoo',
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$pet->getKey()}", ['weight_lb' => 22.5])
            ->assertOk()
            ->assertJsonPath('data.name', 'Bella')
            ->assertJsonPath('data.breed', 'Cockapoo');
    }

    /**
     * Invariant #4. A pet carries grooming history, appointment history and the notes that make
     * the next groom go well.
     */
    public function test_deleting_a_pet_archives_it_rather_than_removing_it(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/pets/{$pet->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'status' => PetStatus::Archived->value,
            'deleted_at' => null,
        ]);
    }

    /**
     * The worst message this product could generate is a cheerful "time for Bella's groom!" about
     * a dog that has died. §22 reads this flag, so it has to be in the data.
     */
    public function test_a_deceased_pet_stops_allowing_outreach(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$pet->getKey()}", ['status' => PetStatus::Deceased->value])
            ->assertOk()
            ->assertJsonPath('data.allows_outreach', false);

        $this->assertDatabaseHas('audit_events', ['event' => 'pet.marked_deceased']);
    }

    /**
     * Archiving a deceased pet would overwrite the more specific fact with a vaguer one, losing
     * the reason retention must never contact anyone about it.
     */
    public function test_archiving_does_not_overwrite_a_deceased_status(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->deceased()->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/pets/{$pet->getKey()}")
            ->assertNoContent();

        $this->assertSame(PetStatus::Deceased, $pet->refresh()->status);
    }

    /**
     * §9 and §29: health information must never be presented as veterinary diagnosis, so it
     * travels with the caveat attached rather than as a clinical field.
     */
    public function test_medical_notes_are_marked_as_not_being_veterinary_advice(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create([
            'medical_notes' => 'Owner reports a sensitive left ear.',
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/pets/{$pet->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.medical_notes', 'Owner reports a sensitive left ear.')
            ->assertJsonPath('data.medical_notes_are_not_veterinary_advice', true);
    }

    /**
     * Spec §9 gives internal notes their own permission, so they must not be writable through the
     * ordinary edit — that would be a second, ungated way in.
     */
    public function test_internal_notes_cannot_be_written_through_the_ordinary_update(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$pet->getKey()}", [
                'internal_notes' => 'Snuck in through the back door.',
                'breed' => 'Cockapoo',
            ])
            ->assertOk();

        $pet->refresh();
        $this->assertNull($pet->internal_notes);
        $this->assertSame('Cockapoo', $pet->breed);
    }

    /**
     * Re-homing a pet moves its whole grooming history to another family. It must not be a side
     * effect of an ordinary edit.
     */
    public function test_a_pet_cannot_be_moved_to_another_customer_by_mass_assignment(): void
    {
        $other = Customer::factory()->named('Someone', 'Else')->create();
        $pet = Pet::factory()->of($this->customer->getKey())->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/pets/{$pet->getKey()}", [
                'customer_id' => $other->getKey(),
                'breed' => 'Collie',
            ])
            ->assertOk();

        $this->assertSame($this->customer->getKey(), $pet->refresh()->customer_id);
    }

    public function test_creating_updating_and_archiving_are_all_audited(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/pets', [
            'customer_id' => $this->customer->getKey(),
            'name' => 'Bella',
            'species' => PetSpecies::Dog->value,
        ]);

        $pet = Pet::query()->firstOrFail();

        $this->actingAs($this->owner)->putJson("/api/v1/pets/{$pet->getKey()}", ['breed' => 'Collie']);
        $this->actingAs($this->owner)->deleteJson("/api/v1/pets/{$pet->getKey()}");

        $events = AuditEvent::query()->pluck('event')->all();

        $this->assertContains('pet.created', $events);
        $this->assertContains('pet.updated', $events);
        $this->assertContains('pet.archived', $events);
    }

    public function test_the_endpoints_require_authentication(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->create();

        $this->getJson('/api/v1/pets')->assertUnauthorized();
        $this->getJson("/api/v1/pets/{$pet->getKey()}")->assertUnauthorized();
        $this->postJson('/api/v1/pets', ['name' => 'Bella'])->assertUnauthorized();
    }
}
