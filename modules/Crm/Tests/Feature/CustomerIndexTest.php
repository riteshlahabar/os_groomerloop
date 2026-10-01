<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The §8 customer list: search, filter, sort — and §33's server-side pagination.
 *
 * This is the query the front desk lives on, so the tests here are as much about the
 * non-functional requirements as the feature: nothing unbounded, nothing sorted by a
 * user-supplied column name, and a stable order so paging cannot hide a customer.
 */
final class CustomerIndexTest extends TestCase
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

    /**
     * Spec §33. Every list endpoint paginates server-side; the habit starts with the first
     * one rather than being retrofitted in Phase 12.
     */
    public function test_the_list_is_paginated_server_side(): void
    {
        Customer::factory()->count(30)->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_the_page_size_is_capped(): void
    {
        // Validated rather than silently clamped: a caller that asked for 5,000 rows and got
        // 100 should be told, not left believing the page held everything.
        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?per_page=5000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * An unvalidated sort column reaching orderBy is the one place Eloquent will interpolate
     * user input into SQL.
     */
    public function test_only_whitelisted_columns_can_be_sorted_on(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?sort=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?sort=name&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('direction');
    }

    public function test_customers_are_ordered_by_last_name_then_first_name_by_default(): void
    {
        Customer::factory()->named('Beth', 'Adams')->create();
        Customer::factory()->named('Alice', 'Adams')->create();
        Customer::factory()->named('Zoe', 'Abbott')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('data.0.full_name', 'Zoe Abbott')
            ->assertJsonPath('data.1.full_name', 'Alice Adams')
            ->assertJsonPath('data.2.full_name', 'Beth Adams');
    }

    /**
     * Without a stable tiebreak two identically-named customers can swap places between
     * page 1 and page 2, and one of them is then never seen.
     */
    public function test_paging_through_identical_names_shows_every_customer_exactly_once(): void
    {
        Customer::factory()->count(4)->named('John', 'Smith')->create();

        $seen = [];

        for ($page = 1; $page <= 4; $page++) {
            $seen[] = $this->actingAs($this->owner)
                ->getJson("/api/v1/customers?per_page=1&page={$page}")
                ->assertOk()
                ->json('data.0.id');
        }

        $this->assertCount(4, array_unique($seen));
    }

    public function test_search_matches_a_full_name_typed_into_one_box(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();
        Customer::factory()->named('John', 'Smith')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=Jane+Doe')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Jane Doe');
    }

    public function test_search_finds_a_customer_by_a_differently_formatted_phone_number(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create(['phone' => '(512) 555-0134']);

        // Typed without the punctuation, which is how anyone reading a number off a phone
        // screen would type it.
        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=5125550134')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Jane Doe');
    }

    public function test_search_finds_a_customer_by_email_regardless_of_case(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=JANE@EXAMPLE.TEST')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * A wildcard typed into the search box must be a literal, not a pattern that matches the
     * entire book.
     */
    public function test_a_percent_sign_in_the_search_term_is_treated_literally(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Invariant #4: archived customers are hidden, never gone.
     */
    public function test_archived_customers_are_hidden_by_default_and_findable_on_request(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();
        Customer::factory()->named('Gone', 'Away')->archived()->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Jane Doe');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?include_archived=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?status[]=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Gone Away');
    }

    public function test_customers_can_be_filtered_by_status_and_source(): void
    {
        Customer::factory()->lead()->create(['source' => CustomerSource::Website]);
        Customer::factory()->create(['source' => CustomerSource::WalkIn]);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?status[]=lead')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'lead');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?source[]=website')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'website');
    }

    /**
     * "Tagged nervous AND senior" is the useful filter. A single whereIn would return anyone
     * with either, which is a different question.
     */
    public function test_filtering_by_several_tags_requires_all_of_them(): void
    {
        $nervous = CustomerTag::factory()->named('Nervous')->create();
        $senior = CustomerTag::factory()->named('Senior')->create();

        $both = Customer::factory()->named('Both', 'Tags')->create();
        $one = Customer::factory()->named('One', 'Tag')->create();

        $pivot = ['tenant_id' => $this->tenant->getKey()];
        $both->tags()->attach([$nervous->getKey() => $pivot, $senior->getKey() => $pivot]);
        $one->tags()->attach($nervous, $pivot);

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?tags[]=nervous')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?tags[]=nervous&tags[]=senior')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Both Tags');
    }

    /**
     * §22 retention and §13 announcements both need to know who may not be contacted, so
     * the filter is part of the list rather than something a client computes afterwards.
     */
    public function test_customers_can_be_filtered_by_whether_they_have_opted_out(): void
    {
        Customer::factory()->named('Quiet', 'One')->optedOut()->create();
        Customer::factory()->named('Happy', 'Two')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?opted_out=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Quiet One');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?opted_out=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Happy Two');
    }

    /**
     * Tags are rendered on every row, so without eager loading this is an N+1 the moment a
     * business has tagged anyone.
     */
    public function test_the_list_does_not_query_once_per_customer_for_tags(): void
    {
        $tag = CustomerTag::factory()->named('Nervous')->create();

        Customer::factory()->count(5)->create()->each(
            fn (Customer $customer) => $customer->tags()->attach($tag, ['tenant_id' => $this->tenant->getKey()])
        );

        DB::enableQueryLog();

        $this->actingAs($this->owner)->getJson('/api/v1/customers')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $tagQueries = array_filter(
            $queries,
            static fn (array $q): bool => str_contains($q['query'], 'customer_tag')
        );

        // One query for the pivot, not one per customer.
        $this->assertLessThanOrEqual(2, count($tagQueries));
    }
}
