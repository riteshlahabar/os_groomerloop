<?php

namespace Modules\Team\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The §23 team list: filters, whitelisted sort, server-side pagination (§33).
 */
final class StaffIndexTest extends TestCase
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
     * Leavers are hidden from the working list by default but never deleted (invariant #4).
     */
    public function test_inactive_staff_are_hidden_by_default(): void
    {
        StaffMember::factory()->named('Active One')->create();
        StaffMember::factory()->named('Left')->inactive()->create();

        $names = $this->actingAs($this->owner)
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->json('data.*.display_name');

        $this->assertSame(['Active One'], $names);
    }

    public function test_include_inactive_shows_everyone(): void
    {
        StaffMember::factory()->named('Active One')->create();
        StaffMember::factory()->named('Left')->inactive()->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?include_inactive=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_bookable_online_filters_to_publicly_bookable_staff(): void
    {
        StaffMember::factory()->named('Public')->create();
        StaffMember::factory()->named('Internal Only')->notBookableOnline()->create();

        $names = $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?bookable_online=1')
            ->assertOk()
            ->json('data.*.display_name');

        $this->assertSame(['Public'], $names);
    }

    /**
     * The list an owner needs when nobody can book Maria: active staff with no rota at all.
     */
    public function test_without_working_hours_finds_staff_with_no_rota(): void
    {
        $withHours = StaffMember::factory()->named('Has Rota')->create();
        $withHours->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);

        StaffMember::factory()->named('No Rota')->create();

        $names = $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?without_working_hours=1')
            ->assertOk()
            ->json('data.*.display_name');

        $this->assertSame(['No Rota'], $names);
    }

    public function test_has_login_filters_by_whether_an_account_is_linked(): void
    {
        $user = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        StaffMember::factory()->named('Linked')->linkedToUser($user->getKey())->create();
        StaffMember::factory()->named('Unlinked')->create();

        $names = $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?has_login=1')
            ->assertOk()
            ->json('data.*.display_name');

        $this->assertSame(['Linked'], $names);
    }

    public function test_search_matches_name_job_title_or_email(): void
    {
        StaffMember::factory()->named('Maria Lopez')->create();
        StaffMember::factory()->named('Devon Clarke')->create();

        $names = $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?search=Maria')
            ->assertOk()
            ->json('data.*.display_name');

        $this->assertSame(['Maria Lopez'], $names);
    }

    public function test_an_unknown_sort_column_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?sort=secret_column')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_the_list_is_paginated_server_side(): void
    {
        StaffMember::factory()->count(3)->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/staff?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }
}
