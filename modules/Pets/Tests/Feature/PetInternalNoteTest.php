<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Http\Resources\PetResource;
use Modules\Pets\Models\Pet;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec §9's "internal staff notes with permissions" — the visibility level that makes pet notes
 * four columns instead of one.
 *
 * The rule cuts in an unusual direction and that is the point: a Groomer holds this permission
 * while holding no ability to edit anything else on the pet, because "bit a groomer in March,
 * muzzle for nails" is safety information and the person with the clippers is both who needs to
 * read it and who learns it. Marketing does not hold it at all — §5 scopes that role to growth
 * modules, and staff commentary about a customer's dog has no business in a campaign tool.
 */
final class PetInternalNoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();

        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create();
        $this->pet = Pet::factory()->of($this->customer->getKey())->named('Bella')->create();
    }

    public function test_an_internal_note_can_be_recorded(): void
    {
        $this->actingAs($this->userWith(Role::Owner))
            ->putJson("/api/v1/pets/{$this->pet->getKey()}/internal-notes", [
                'internal_notes' => 'Muzzle for nail trims. Bit a groomer in March.',
            ])
            ->assertOk()
            ->assertJsonPath('data.internal_notes', 'Muzzle for nail trims. Bit a groomer in March.');

        $this->assertSame(
            'Muzzle for nail trims. Bit a groomer in March.',
            $this->pet->refresh()->internal_notes
        );
    }

    /**
     * An explicit null clears a note written in error — which is why the rule is `present` and
     * `nullable` rather than `required`.
     */
    public function test_an_internal_note_can_be_cleared(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->withInternalNote('Old note.')->create();

        $this->actingAs($this->userWith(Role::Owner))
            ->putJson("/api/v1/pets/{$pet->getKey()}/internal-notes", ['internal_notes' => null])
            ->assertOk()
            ->assertJsonPath('data.internal_notes', null);

        $this->assertNull($pet->refresh()->internal_notes);
    }

    public function test_the_field_must_be_sent_at_all(): void
    {
        $this->actingAs($this->userWith(Role::Owner))
            ->putJson("/api/v1/pets/{$this->pet->getKey()}/internal-notes", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('internal_notes');
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatHandleTheAnimal(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],

            // The interesting one: read and write here, no pets.manage at all.
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
        ];
    }

    #[DataProvider('rolesThatHandleTheAnimal')]
    public function test_everyone_who_handles_the_animal_can_read_and_write_the_notes(Role $role): void
    {
        $user = $this->userWith($role);

        $this->actingAs($user)
            ->putJson("/api/v1/pets/{$this->pet->getKey()}/internal-notes", [
                'internal_notes' => 'Nervous around the dryer.',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->getJson("/api/v1/pets/{$this->pet->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.internal_notes', 'Nervous around the dryer.');
    }

    /**
     * A groomer cannot edit the pet record, and still writes its handling history. Worth asserting
     * together, because the two permissions being separate is the whole design.
     */
    public function test_a_groomer_writes_notes_without_being_able_to_edit_the_pet(): void
    {
        $groomer = $this->userWith(Role::Groomer);

        $this->actingAs($groomer)
            ->putJson("/api/v1/pets/{$this->pet->getKey()}", ['breed' => 'Collie'])
            ->assertForbidden();

        $this->actingAs($groomer)
            ->putJson("/api/v1/pets/{$this->pet->getKey()}/internal-notes", [
                'internal_notes' => 'Muzzle for nails.',
            ])
            ->assertOk();
    }

    /**
     * Marketing does not reach pet records at all — §5 scopes that role to growth modules, so it
     * holds neither `pets.view` nor `pets.internal_notes`. Refused before the question of what the
     * response would have contained ever arises.
     */
    public function test_marketing_cannot_reach_pet_records_at_all(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())
            ->withInternalNote('Owner argues about pricing every visit.')
            ->create();

        $marketing = $this->userWith(Role::Marketing);

        $this->actingAs($marketing)
            ->putJson("/api/v1/pets/{$pet->getKey()}/internal-notes", ['internal_notes' => 'x'])
            ->assertForbidden();

        $this->actingAs($marketing)
            ->getJson("/api/v1/pets/{$pet->getKey()}")
            ->assertForbidden();

        $this->actingAs($marketing)->getJson('/api/v1/pets')->assertForbidden();
    }

    /**
     * The resource gate, tested directly.
     *
     * Every role that currently holds `pets.view` also holds `pets.internal_notes`, so no route
     * today can reach a pet and be refused its notes — which is exactly why this is asserted at
     * the resource rather than through HTTP. The two cases it protects are both coming: §31
     * platform support access, which should see a record without staff commentary about a
     * customer, and the §12 public booking flow, where the viewer is not a user at all.
     *
     * Omitted rather than null: a key that is sometimes null and sometimes absent tells a reader
     * nothing, while a key that only appears for those allowed to see it cannot be leaked by a
     * client that forgot to check.
     */
    public function test_the_resource_omits_the_note_for_a_viewer_without_the_permission(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->withInternalNote('Staff only.')->create();

        $allowed = Request::create('/');
        $allowed->setUserResolver(fn (): User => $this->userWith(Role::Groomer));

        $refused = Request::create('/');
        $refused->setUserResolver(fn (): User => $this->userWith(Role::Marketing));

        $anonymous = Request::create('/');

        $this->assertSame(
            'Staff only.',
            PetResource::make($pet)->toArray($allowed)['internal_notes'] ?? null
        );

        $this->assertArrayNotHasKey('internal_notes', PetResource::make($pet)->toArray($refused));
        $this->assertArrayNotHasKey('internal_notes', PetResource::make($pet)->toArray($anonymous));
    }

    /**
     * The other half of the same guarantee: a role that IS allowed sees the note in the list, not
     * only on the single record.
     */
    public function test_the_note_is_present_in_the_list_for_a_role_that_holds_the_permission(): void
    {
        Pet::factory()->of($this->customer->getKey())->withInternalNote('Staff only.')->create();

        $this->actingAs($this->userWith(Role::Groomer))
            ->getJson('/api/v1/pets')
            ->assertOk()
            ->assertJsonPath('data.1.internal_notes', 'Staff only.');
    }

    /**
     * Invariant #8, with a deliberate limit: the note text is not copied into the audit payload.
     * It is the one field in the module with its own permission, and an audit log readable by
     * anyone holding `audit.view` would be a second, ungated copy of it.
     */
    public function test_the_change_is_audited_without_copying_the_note_into_the_log(): void
    {
        $owner = $this->userWith(Role::Owner);

        $this->actingAs($owner)
            ->putJson("/api/v1/pets/{$this->pet->getKey()}/internal-notes", [
                'internal_notes' => 'Sensitive commentary about the owner.',
            ])
            ->assertOk();

        $event = AuditEvent::query()->where('event', 'pet.internal_note_recorded')->firstOrFail();

        $this->assertSame($owner->getKey(), $event->user_id);
        $this->assertFalse($event->properties['cleared']);
        $this->assertFalse($event->properties['had_previous_note']);

        $this->assertStringNotContainsString(
            'Sensitive commentary',
            json_encode($event->properties, JSON_THROW_ON_ERROR)
        );
    }

    public function test_writing_the_same_note_again_records_nothing(): void
    {
        $pet = Pet::factory()->of($this->customer->getKey())->withInternalNote('Same note.')->create();

        $this->actingAs($this->userWith(Role::Owner))
            ->putJson("/api/v1/pets/{$pet->getKey()}/internal-notes", ['internal_notes' => 'Same note.'])
            ->assertOk();

        $this->assertDatabaseMissing('audit_events', ['event' => 'pet.internal_note_recorded']);
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }
}
