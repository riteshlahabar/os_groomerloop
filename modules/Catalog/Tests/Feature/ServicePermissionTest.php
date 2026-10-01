<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Service;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Models\Plan;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may read and who may change the price list (spec §5, §10).
 *
 * The line that matters: everyone who works in the salon can read the menu — a groomer needs to know
 * what a "full groom" includes and how long it is booked for — but only Owner and Manager may change
 * it. A front desk that could reprice a groom is a business that cannot reconcile its own takings.
 */
final class ServicePermissionTest extends TestCase
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
    public static function rolesThatReadTheMenu(): array
    {
        return [
            'owner' => [Role::Owner],
            'manager' => [Role::Manager],
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
        ];
    }

    #[DataProvider('rolesThatReadTheMenu')]
    public function test_everyone_working_in_the_salon_can_read_the_menu(Role $role): void
    {
        $service = Service::factory()->create();
        $user = $this->userWith($role);

        $this->actingAs($user)->getJson('/api/v1/services')->assertOk();
        $this->actingAs($user)->getJson("/api/v1/services/{$service->getKey()}")->assertOk();
        $this->actingAs($user)->getJson('/api/v1/service-categories')->assertOk();
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesThatMayNotChangeThePriceList(): array
    {
        return [
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
            'marketing' => [Role::Marketing],
        ];
    }

    #[DataProvider('rolesThatMayNotChangeThePriceList')]
    public function test_only_an_owner_or_manager_may_change_the_price_list(Role $role): void
    {
        $service = Service::factory()->create();
        $user = $this->userWith($role);

        $this->actingAs($user)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/services/{$service->getKey()}", ['price' => '1.00'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson("/api/v1/services/{$service->getKey()}")
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", ['windows' => []])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/v1/service-categories', ['name' => 'Extras'])
            ->assertForbidden();
    }

    public function test_a_manager_can_run_the_menu(): void
    {
        $user = $this->userWith(Role::Manager);

        $this->actingAs($user)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
            ])
            ->assertCreated();

        $service = Service::query()->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/services/{$service->getKey()}", ['price' => '70.00'])
            ->assertOk();
    }

    /**
     * §5 scopes Marketing to growth modules, and it holds no `services.view` — so it cannot read the
     * menu either, not merely edit it.
     */
    public function test_marketing_cannot_read_the_menu(): void
    {
        $service = Service::factory()->create();
        $user = $this->userWith(Role::Marketing);

        $this->actingAs($user)->getJson('/api/v1/services')->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/services/{$service->getKey()}")->assertForbidden();
    }

    /**
     * Unlike Crm and Pets, these routes carry no `entitlement:`. §25 does not gate the catalogue
     * behind a plan and could not sensibly do so — a business that cannot define what it sells
     * cannot use the product at all, whatever it pays. Asserted so that adding a gate later is a
     * deliberate act with a failing test behind it.
     */
    public function test_the_catalogue_is_reachable_without_any_plan_at_all(): void
    {
        Plan::query()->update(['is_default' => false]);

        $tenant = Tenant::factory()->create();

        $user = app(TenantContext::class)->runFor(
            $tenant,
            fn (): User => User::factory()->memberOf($tenant, Role::Owner)->create()
        );

        $this->actingAs($user)->getJson('/api/v1/services')->assertOk();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }
}
