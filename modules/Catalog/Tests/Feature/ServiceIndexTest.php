<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Models\ServiceCategory;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The §10 service list, and §33's server-side pagination.
 */
final class ServiceIndexTest extends TestCase
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

    public function test_the_list_is_paginated_server_side(): void
    {
        Service::factory()->count(30)->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?per_page=5000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_only_whitelisted_columns_can_be_sorted_on(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?sort=price_cents')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    /**
     * The default order is the salon's own: position, then name. A price list is arranged
     * deliberately — the thing they most want to sell goes first — and alphabetical is nobody's menu.
     */
    public function test_the_default_order_is_the_businesss_own_menu_order(): void
    {
        Service::factory()->named('Zebra Trim')->create(['position' => 1]);
        Service::factory()->named('Full Groom')->create(['position' => 2]);
        Service::factory()->named('Bath & Brush')->create(['position' => 3]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Zebra Trim')
            ->assertJsonPath('data.1.name', 'Full Groom')
            ->assertJsonPath('data.2.name', 'Bath & Brush');
    }

    public function test_services_can_be_sorted_by_price_and_duration(): void
    {
        Service::factory()->named('Cheap')->priced(20)->lasting(30)->create();
        Service::factory()->named('Dear')->priced(120)->lasting(120)->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?sort=price&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Dear');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?sort=duration')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Cheap');
    }

    public function test_services_can_be_found_by_name_or_description(): void
    {
        Service::factory()->named('Full Groom')->create(['description' => 'Includes a hand strip.']);
        Service::factory()->named('Nail Trim')->create(['description' => 'Nails only.']);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?search=hand+strip')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Full Groom');
    }

    /**
     * Invariant #4: retired services are hidden from the working menu, never deleted.
     */
    public function test_inactive_services_are_hidden_by_default_and_findable_on_request(): void
    {
        Service::factory()->named('Full Groom')->create();
        Service::factory()->named('Retired Groom')->inactive()->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Full Groom');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?status[]=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Retired Groom');
    }

    /**
     * Three states, not two: absent leaves the menu mixed, which is what a search across everything
     * wants. A boolean default would make "only bookable services" impossible to ask for.
     */
    public function test_add_ons_can_be_listed_separately_from_services(): void
    {
        Service::factory()->named('Full Groom')->create();
        Service::factory()->addOn()->named('Nail Trim')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?is_add_on=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nail Trim');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?is_add_on=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Full Groom');
    }

    /**
     * What the §12 public booking page may show, computed server-side. Never trust a client to
     * filter this: an inactive or unpublished service reappearing on a booking page is a customer
     * buying something the salon does not sell.
     */
    public function test_the_online_bookable_filter_excludes_add_ons_and_unpublished_services(): void
    {
        Service::factory()->named('Full Groom')->create();
        Service::factory()->named('Hand Strip')->notBookableOnline()->create();
        Service::factory()->addOn()->named('Nail Trim')->create();
        Service::factory()->named('Retired')->inactive()->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?bookable_online=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Full Groom');
    }

    public function test_services_can_be_filtered_by_category(): void
    {
        $grooming = ServiceCategory::factory()->named('Grooming')->create();
        $extras = ServiceCategory::factory()->named('Extras')->create();

        Service::factory()->named('Full Groom')->inCategory($grooming->getKey())->create();
        Service::factory()->named('Nail Trim')->inCategory($extras->getKey())->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/services?category_id={$grooming->getKey()}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Full Groom');
    }

    /**
     * Categories and add-ons are rendered on every row, so without eager loading this is an N+1 the
     * moment a business has organised its menu — and `Model::shouldBeStrict()` throws on it in
     * development rather than letting it ship.
     */
    public function test_the_list_does_not_query_once_per_service_for_categories_and_add_ons(): void
    {
        $category = ServiceCategory::factory()->create();
        $addOn = Service::factory()->addOn()->create();

        Service::factory()->count(5)->inCategory($category->getKey())->create()->each(
            fn (Service $service) => $service->addOns()->attach($addOn, ['tenant_id' => $this->tenant->getKey()])
        );

        DB::enableQueryLog();

        $this->actingAs($this->owner)->getJson('/api/v1/services')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // The page, plus one for categories and one for add-ons. Not one per row.
        $this->assertLessThanOrEqual(6, count($queries));
    }
}
