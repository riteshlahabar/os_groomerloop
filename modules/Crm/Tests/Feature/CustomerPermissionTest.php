<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may do what to the customer book (spec §5, §8).
 *
 * The distinction the spec draws, and the one worth testing: a front desk that adds and edits
 * customers all day is doing its job, while the same person downloading the entire book as a
 * spreadsheet is a different risk. So import and export have their own permissions, held by
 * Owner and Manager only.
 */
final class CustomerPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();

        app(TenantContext::class)->set($this->tenant);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatSeeCustomers(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
            'marketing' => [Role::Marketing],
        ];
    }

    /**
     * Everyone who works in the business can see the customer book. A groomer who cannot look
     * up whose dog is arriving cannot do the job.
     */
    #[DataProvider('rolesThatSeeCustomers')]
    public function test_every_working_role_can_read_the_customer_book(Role $role): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWith($role))
            ->getJson('/api/v1/customers')
            ->assertOk();

        $this->actingAs($this->userWith($role))
            ->getJson("/api/v1/customers/{$customer->getKey()}")
            ->assertOk();
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatMayNotWrite(): array
    {
        return [
            'groomer' => [Role::Groomer],
            'marketing' => [Role::Marketing],
        ];
    }

    #[DataProvider('rolesThatMayNotWrite')]
    public function test_a_read_only_role_cannot_change_a_customer(Role $role): void
    {
        $customer = Customer::factory()->create();
        $user = $this->userWith($role);

        $this->actingAs($user)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane'])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Austin'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/v1/customers/{$customer->getKey()}")
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['marketing' => true])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson("/api/v1/customers/{$customer->getKey()}/merge", ['merge_customer_id' => 1])
            ->assertForbidden();
    }

    public function test_the_front_desk_can_run_the_book_day_to_day(): void
    {
        $user = $this->userWith(Role::FrontDesk);

        $this->actingAs($user)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane'])
            ->assertCreated();

        $customer = Customer::query()->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Austin'])
            ->assertOk();
    }

    /**
     * The §8 split that matters: moving the whole customer book in or out is a different risk
     * from editing one record, and the front desk does the second all day without needing the
     * first.
     */
    public function test_the_front_desk_cannot_import_or_export_the_whole_book(): void
    {
        $user = $this->userWith(Role::FrontDesk);

        $this->actingAs($user)
            ->postJson('/api/v1/customers/import', ['rows' => [['first_name' => 'Jane']]])
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/v1/customers-export')
            ->assertForbidden();
    }

    public function test_a_manager_can_import_and_export(): void
    {
        $user = $this->userWith(Role::Manager);

        $this->actingAs($user)
            ->postJson('/api/v1/customers/import', ['rows' => [['first_name' => 'Jane']]])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/customers-export')
            ->assertOk();
    }

    /**
     * Marketing sees the book — §17 growth and §22 retention need it — but may not edit it and
     * certainly may not walk out with it.
     */
    public function test_marketing_can_read_the_book_but_not_export_it(): void
    {
        $user = $this->userWith(Role::Marketing);

        $this->actingAs($user)->getJson('/api/v1/customers')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/customers-export')->assertForbidden();
    }

    public function test_tag_management_needs_the_write_permission(): void
    {
        $groomer = $this->userWith(Role::Groomer);

        // Reading the vocabulary is fine: a groomer sees tags on the customers they look at.
        $this->actingAs($groomer)->getJson('/api/v1/customer-tags')->assertOk();

        $this->actingAs($groomer)
            ->postJson('/api/v1/customer-tags', ['name' => 'Nervous'])
            ->assertForbidden();
    }

    /**
     * Invariant #3, and D-012's fail-closed rule. A business whose plan cannot be determined
     * gets nothing rather than everything — and a plan refusal is 402, not 403, because "ask
     * your owner" and "upgrade your plan" are different instructions to the person reading it.
     */
    public function test_a_business_with_no_determinable_plan_is_refused_with_402(): void
    {
        Plan::query()->update(['is_default' => false]);

        $user = $this->ownerOfAnotherBusiness();

        $this->actingAs($user)->getJson('/api/v1/customers')->assertStatus(402);
        $this->actingAs($user)->postJson('/api/v1/customers', ['first_name' => 'Jane'])->assertStatus(402);
    }

    /**
     * Spec §25 gives CRM to every plan, so the gate never actually refuses a paying business
     * today. It is declared anyway because the matrix is data and packaging can change without
     * a deploy — a route that assumed "every plan has this" would be the thing someone had to
     * find and edit the day it stopped being true.
     */
    public function test_the_default_plan_includes_the_customer_book(): void
    {
        $user = $this->ownerOfAnotherBusiness();

        $this->assertNull($user->tenant->plan_id);

        $this->actingAs($user)->getJson('/api/v1/customers')->assertOk();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }

    /**
     * A business that has never chosen a plan, built inside its own tenant context.
     *
     * BelongsToTenant refuses to create a record for a tenant other than the one currently in
     * context — the guard that makes invariant #1 hold for writes as well as reads — so the
     * user has to be created inside runFor rather than alongside the setUp tenant.
     */
    private function ownerOfAnotherBusiness(): User
    {
        $tenant = Tenant::factory()->create();

        return app(TenantContext::class)->runFor(
            $tenant,
            fn (): User => User::factory()->memberOf($tenant, Role::Owner)->create()
        );
    }
}
