<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Actions\MergeCustomers;
use Modules\Crm\Contracts\CustomerMergeParticipant;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Crm\Services\MergeParticipants;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Merging two customers (spec §8) — the CRM operation with the most ways to lose data.
 */
final class CustomerMergeTest extends TestCase
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

    public function test_the_survivor_keeps_everything_it_already_had(): void
    {
        $survivor = Customer::factory()->create([
            'first_name' => 'Jane',
            'phone' => '512-555-0134',
            'city' => 'Austin',
        ]);

        $loser = Customer::factory()->create([
            'first_name' => 'Janet',
            'phone' => '512-555-9999',
            'city' => 'Dallas',
        ]);

        app(MergeCustomers::class)->execute($survivor, $loser);

        // Nobody merging two records expects the one they kept to change its phone number.
        $survivor->refresh();
        $this->assertSame('Jane', $survivor->first_name);
        $this->assertSame('512-555-0134', $survivor->phone);
        $this->assertSame('Austin', $survivor->city);
    }

    public function test_the_loser_fills_in_only_what_the_survivor_was_missing(): void
    {
        $survivor = Customer::factory()->create(['email' => 'jane@example.test', 'city' => null]);
        $loser = Customer::factory()->create(['email' => 'other@example.test', 'city' => 'Austin']);

        app(MergeCustomers::class)->execute($survivor, $loser);

        $survivor->refresh();
        $this->assertSame('jane@example.test', $survivor->email);
        $this->assertSame('Austin', $survivor->city);
    }

    public function test_notes_from_both_records_survive(): void
    {
        $survivor = Customer::factory()->create(['notes' => 'Prefers morning slots.']);
        $loser = Customer::factory()->create(['notes' => 'Dog is nervous of clippers.']);

        app(MergeCustomers::class)->execute($survivor, $loser);

        $notes = $survivor->refresh()->notes;

        // Both kept, and visibly separated — reading one as context for the other would be
        // worse than losing it.
        $this->assertStringContainsString('Prefers morning slots.', $notes);
        $this->assertStringContainsString('Dog is nervous of clippers.', $notes);
        $this->assertStringContainsString('Merged from duplicate record', $notes);
    }

    /**
     * Invariant #9. An opt-out must not be lost to a data-tidying operation.
     */
    public function test_an_opt_out_on_either_record_survives_the_merge(): void
    {
        $survivor = Customer::factory()->fullyConsented()->create();
        $loser = Customer::factory()->fullyConsented()->optedOut()->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertTrue($survivor->refresh()->hasOptedOut());
    }

    public function test_consent_takes_the_most_restrictive_answer_of_the_two(): void
    {
        $survivor = Customer::factory()->fullyConsented()->create();
        $loser = Customer::factory()->create([
            'accepts_email' => true,
            'accepts_sms' => false,
            'accepts_marketing' => false,
        ]);

        app(MergeCustomers::class)->execute($survivor, $loser);

        $survivor->refresh();

        // Email agreed by both, so it survives. SMS and marketing were refused on one
        // record, so they are refused on the merged one.
        $this->assertTrue($survivor->accepts_email);
        $this->assertFalse($survivor->accepts_sms);
        $this->assertFalse($survivor->accepts_marketing);
    }

    public function test_tags_from_both_records_are_kept(): void
    {
        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        $nervous = CustomerTag::factory()->named('Nervous')->create();
        $senior = CustomerTag::factory()->named('Senior')->create();

        $survivor->tags()->attach($nervous, ['tenant_id' => $this->tenant->getKey()]);
        $loser->tags()->attach($senior, ['tenant_id' => $this->tenant->getKey()]);

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertEqualsCanonicalizing(
            ['Nervous', 'Senior'],
            $survivor->refresh()->tags->pluck('name')->all()
        );
    }

    /**
     * Invariant #4. A merge done in error must be recoverable.
     */
    public function test_the_merged_record_is_soft_deleted_not_destroyed(): void
    {
        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertSoftDeleted('customers', ['id' => $loser->getKey()]);
        $this->assertDatabaseHas('customers', ['id' => $loser->getKey()]);
    }

    /**
     * The extension point Pets, Scheduling and Notifications will each use.
     */
    public function test_other_modules_move_their_own_records(): void
    {
        $moved = [];

        app(MergeParticipants::class)->register(
            new class($moved) implements CustomerMergeParticipant
            {
                /** @param array<string, mixed> $moved */
                public function __construct(private array &$moved) {}

                public function describes(): string
                {
                    return 'pets';
                }

                public function transfer(int $fromCustomerId, int $toCustomerId): int
                {
                    $this->moved[] = [$fromCustomerId, $toCustomerId];

                    return 3;
                }
            }
        );

        $survivor = Customer::factory()->create();
        $loser = Customer::factory()->create();

        app(MergeCustomers::class)->execute($survivor, $loser);

        $this->assertSame([[$loser->getKey(), $survivor->getKey()]], $moved);

        $event = AuditEvent::query()->where('event', 'customer.merged')->firstOrFail();
        $this->assertSame(['pets' => 3], $event->properties['records_moved']);
    }

    public function test_a_customer_cannot_be_merged_into_itself(): void
    {
        $customer = Customer::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(MergeCustomers::class)->execute($customer, $customer);
    }

    // --- Over HTTP ------------------------------------------------------------------------

    public function test_an_owner_can_merge_through_the_api(): void
    {
        $survivor = Customer::factory()->create(['first_name' => 'Jane']);
        $loser = Customer::factory()->create(['first_name' => 'Janet']);

        $this->actingAs($this->owner)
            ->postJson("/api/v1/customers/{$survivor->getKey()}/merge", [
                'merge_customer_id' => $loser->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $survivor->getKey())
            ->assertJsonPath('data.first_name', 'Jane');

        $this->assertSoftDeleted('customers', ['id' => $loser->getKey()]);
    }

    /**
     * Invariant #1, on a path that does not go through route model binding — the id comes
     * from the request body, so the tenant scope is the only thing protecting it.
     */
    public function test_a_customer_from_another_business_cannot_be_merged_in(): void
    {
        $survivor = Customer::factory()->create();

        $otherTenant = Tenant::factory()->create();
        $stranger = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => Customer::factory()->create()
        );

        $this->actingAs($this->owner)
            ->postJson("/api/v1/customers/{$survivor->getKey()}/merge", [
                'merge_customer_id' => $stranger->getKey(),
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('customers', [
            'id' => $stranger->getKey(),
            'deleted_at' => null,
        ]);
    }
}
