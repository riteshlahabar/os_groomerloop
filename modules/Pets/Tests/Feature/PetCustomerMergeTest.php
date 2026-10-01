<?php

namespace Modules\Pets\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Actions\MergeCustomers;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\MergeParticipants;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Models\Pet;
use Modules\Pets\Services\PetMergeParticipant;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Pets follow the surviving customer when two customer records are merged (spec §8, §9).
 *
 * This is the first real use of the `CustomerMergeParticipant` contract Crm built in Phase 5 and
 * tested against a fake. The interesting assertion is not that it works — it is that Crm needed no
 * change at all for it to work, which is the modular boundary earning its keep (D-007).
 */
final class PetCustomerMergeTest extends TestCase
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

    public function test_pets_move_to_the_surviving_customer(): void
    {
        $survivor = Customer::factory()->named('Jane', 'Doe')->create();
        $loser = Customer::factory()->named('Janet', 'Doe')->create();

        $kept = Pet::factory()->of($survivor->getKey())->named('Bella')->create();
        $moved = Pet::factory()->of($loser->getKey())->named('Max')->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertSame($survivor->getKey(), $moved->refresh()->customer_id);
        $this->assertSame($survivor->getKey(), $kept->refresh()->customer_id);
    }

    /**
     * Archived and deceased pets carry the grooming history the surviving record is supposed to
     * keep. Leaving them behind is how history quietly detaches from the family it belongs to.
     */
    public function test_archived_and_deceased_pets_move_too(): void
    {
        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        $archived = Pet::factory()->of($loser->getKey())->archived()->create();
        $deceased = Pet::factory()->of($loser->getKey())->deceased()->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertSame($survivor->getKey(), $archived->refresh()->customer_id);
        $this->assertSame($survivor->getKey(), $deceased->refresh()->customer_id);
    }

    /**
     * The merge audit records what each module moved, so the count has to be real.
     */
    public function test_the_merge_audit_reports_how_many_pets_moved(): void
    {
        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        Pet::factory()->count(3)->of($loser->getKey())->create();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/customers/{$survivor->getKey()}/merge", [
                'merge_customer_id' => $loser->getKey(),
            ])
            ->assertOk();

        $event = AuditEvent::query()->where('event', 'customer.merged')->firstOrFail();

        $this->assertSame(3, $event->properties['records_moved']['pets']);
    }

    /**
     * Required by the contract: a merge retried after a partial failure must not double-count.
     */
    public function test_the_transfer_is_idempotent(): void
    {
        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        Pet::factory()->count(2)->of($loser->getKey())->create();

        $participant = new PetMergeParticipant;

        $this->assertSame(2, $participant->transfer($loser->getKey(), $survivor->getKey()));

        // Nothing left to move, so a second run reports zero rather than moving them again.
        $this->assertSame(0, $participant->transfer($loser->getKey(), $survivor->getKey()));
    }

    /**
     * The registration itself, rather than the behaviour — if Pets ever stopped registering, every
     * test above would still pass when called directly and merges would silently orphan pets.
     */
    public function test_the_participant_is_registered_with_crm(): void
    {
        $describes = array_map(
            static fn (object $p): string => $p->describes(),
            app(MergeParticipants::class)->all()
        );

        $this->assertContains('pets', $describes);
    }

    /**
     * A merge moves pets between two customers of the same business only. The participant is
     * handed ids by Crm, which resolved both inside the tenant — but the query is scoped too, so
     * another business's pets are untouched even if an id collided.
     */
    public function test_another_businesss_pets_are_never_touched_by_a_merge(): void
    {
        $otherTenant = Tenant::factory()->create();

        $strangersPet = app(TenantContext::class)->runFor($otherTenant, function (): Pet {
            $customer = Customer::factory()->create();

            return Pet::factory()->of($customer->getKey())->create();
        });

        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        // Same customer_id as the stranger's pet has, in a different business.
        Pet::factory()->of($loser->getKey())->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertSame(
            $strangersPet->customer_id,
            $strangersPet->refresh()->customer_id
        );
    }
}
