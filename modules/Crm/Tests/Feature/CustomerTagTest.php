<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The business's tag vocabulary (spec §8).
 *
 * Two ways in — a receptionist typing a tag onto a customer, and an owner curating the list on
 * a settings screen — and they must agree, because the unique index on (tenant_id, slug) turns
 * any disagreement into a 500.
 */
final class CustomerTagTest extends TestCase
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

    public function test_a_tag_can_be_created(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', ['name' => 'Nervous', 'colour' => '#3366cc'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nervous')
            // The slug is what the index filter takes, so the client never has to derive it.
            ->assertJsonPath('data.slug', 'nervous');
    }

    /**
     * Asking twice for the same tag returns the same tag rather than colliding on the unique
     * index — and answers 200 rather than 201, so a client can tell nothing new was made.
     */
    public function test_asking_for_the_same_tag_twice_returns_the_same_tag(): void
    {
        $first = $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', ['name' => 'Nervous'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', ['name' => 'nervous'])
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertDatabaseCount('customer_tags', 1);
    }

    /**
     * "!!!" and "???" both slug to the empty string, so without this they would be the same
     * tag and the second would be a 500 rather than a validation error.
     */
    public function test_a_name_with_nothing_to_slug_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', ['name' => '!!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('customer_tags', 0);
    }

    /**
     * Free text here ends up interpolated into a style attribute by some future component.
     */
    public function test_a_colour_must_be_a_hex_value(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', [
                'name' => 'Nervous',
                'colour' => 'red; background: url(javascript:alert(1))',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('colour');
    }

    public function test_the_tag_list_is_paginated_and_ordered_by_name(): void
    {
        CustomerTag::factory()->named('Senior')->create();
        CustomerTag::factory()->named('Aggressive')->create();
        CustomerTag::factory()->named('Nervous')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customer-tags')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Aggressive')
            ->assertJsonPath('data.1.name', 'Nervous')
            ->assertJsonPath('data.2.name', 'Senior')
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * Invariant #4. Removing a label from the vocabulary must not remove the customers wearing
     * it — the pivot cascades, the customers do not.
     */
    public function test_deleting_a_tag_detaches_it_without_touching_the_customers(): void
    {
        $tag = CustomerTag::factory()->named('Nervous')->create();
        $customer = Customer::factory()->create();
        $customer->tags()->attach($tag, ['tenant_id' => $this->tenant->getKey()]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/customer-tags/{$tag->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseMissing('customer_tags', ['id' => $tag->getKey()]);
        $this->assertDatabaseCount('customer_tag', 0);
        $this->assertDatabaseHas('customers', ['id' => $customer->getKey(), 'deleted_at' => null]);
    }

    public function test_creating_and_deleting_a_tag_are_audited(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/customer-tags', ['name' => 'Nervous']);

        $tag = CustomerTag::query()->firstOrFail();

        $this->actingAs($this->owner)->deleteJson("/api/v1/customer-tags/{$tag->getKey()}");

        $events = AuditEvent::query()->pluck('event')->all();

        $this->assertContains('customer_tag.created', $events);
        $this->assertContains('customer_tag.deleted', $events);
    }

    /**
     * A tag typed onto a customer and a tag added on the settings screen must land on the same
     * row. This is the case that used to 500: firstOrCreate mass-assigns its lookup key, and
     * `slug` is deliberately not fillable.
     */
    public function test_a_tag_typed_onto_a_customer_is_the_same_tag_as_one_added_deliberately(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customer-tags', ['name' => 'Nervous'])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane', 'tags' => ['NERVOUS']])
            ->assertCreated()
            ->assertJsonPath('data.tags.0.slug', 'nervous');

        $this->assertDatabaseCount('customer_tags', 1);
    }

    /**
     * Tags are per business, like everything else. Two salons both having a "Nervous" tag is
     * two rows, and the unique index is scoped accordingly.
     */
    public function test_two_businesses_can_each_have_a_tag_of_the_same_name(): void
    {
        CustomerTag::factory()->named('Nervous')->create();

        $otherTenant = Tenant::factory()->create();

        $theirs = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => CustomerTag::factory()->named('Nervous')->create()
        );

        $this->assertDatabaseCount('customer_tags', 2);
        $this->assertSame($otherTenant->getKey(), $theirs->tenant_id);
    }
}
