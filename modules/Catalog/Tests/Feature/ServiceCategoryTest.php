<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Models\ServiceCategory;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * How a business groups its menu (spec §10).
 */
final class ServiceCategoryTest extends TestCase
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

    public function test_a_category_can_be_created(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/service-categories', ['name' => 'Full Grooms', 'position' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Full Grooms')
            ->assertJsonPath('data.slug', 'full-grooms');
    }

    /**
     * Asking twice returns the same category rather than colliding on the unique index — and answers
     * 200, so a client can tell nothing new was made.
     */
    public function test_asking_for_the_same_category_twice_returns_the_same_one(): void
    {
        $first = $this->actingAs($this->owner)
            ->postJson('/api/v1/service-categories', ['name' => 'Grooming'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->owner)
            ->postJson('/api/v1/service-categories', ['name' => 'grooming'])
            ->assertOk()
            ->assertJsonPath('data.id', $first);

        $this->assertDatabaseCount('service_categories', 1);
    }

    /**
     * "!!!" and "???" both slug to the empty string, so without this the second would be a 500
     * rather than a validation error — the same trap the CRM's customer tags hit.
     */
    public function test_a_name_with_nothing_to_slug_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/service-categories', ['name' => '!!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('service_categories', 0);
    }

    public function test_categories_are_listed_in_the_businesss_own_order(): void
    {
        ServiceCategory::factory()->named('Extras')->atPosition(3)->create();
        ServiceCategory::factory()->named('Grooming')->atPosition(1)->create();
        ServiceCategory::factory()->named('Bathing')->atPosition(2)->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/service-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Grooming')
            ->assertJsonPath('data.1.name', 'Bathing')
            ->assertJsonPath('data.2.name', 'Extras');
    }

    /**
     * A settings screen needs to warn before retiring a category that still has a menu behind it,
     * without loading that menu.
     */
    public function test_the_listing_counts_the_services_in_each_category(): void
    {
        $category = ServiceCategory::factory()->named('Grooming')->create();
        Service::factory()->count(3)->inCategory($category->getKey())->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/service-categories')
            ->assertOk()
            ->assertJsonPath('data.0.services_count', 3);
    }

    /**
     * Invariant #4. A business reorganising its menu must not be able to delete its own price list
     * as a side effect — the foreign key is nullOnDelete, so the services survive uncategorised.
     */
    public function test_deleting_a_category_leaves_its_services_alone(): void
    {
        $category = ServiceCategory::factory()->named('Grooming')->create();
        $service = Service::factory()->named('Full Groom')->inCategory($category->getKey())->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/service-categories/{$category->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseMissing('service_categories', ['id' => $category->getKey()]);

        $service->refresh();
        $this->assertNull($service->service_category_id);
        $this->assertSame('Full Groom', $service->name);
    }

    public function test_the_audit_records_how_many_services_were_left_uncategorised(): void
    {
        $category = ServiceCategory::factory()->create();
        Service::factory()->count(2)->inCategory($category->getKey())->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/service-categories/{$category->getKey()}")
            ->assertNoContent();

        $event = AuditEvent::query()->where('event', 'service_category.deleted')->firstOrFail();

        $this->assertSame(2, $event->properties['services_left_uncategorised']);
    }

    /**
     * Categories are per business, so two salons can both have a "Grooming" and the unique index is
     * scoped accordingly.
     */
    public function test_two_businesses_can_each_have_a_category_of_the_same_name(): void
    {
        ServiceCategory::factory()->named('Grooming')->create();

        $otherTenant = Tenant::factory()->create();

        $theirs = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => ServiceCategory::factory()->named('Grooming')->create()
        );

        $this->assertDatabaseCount('service_categories', 2);
        $this->assertSame($otherTenant->getKey(), $theirs->tenant_id);
    }
}
