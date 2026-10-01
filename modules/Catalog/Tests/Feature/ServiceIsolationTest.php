<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Models\ServiceCategory;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Invariant #1 for the catalogue, proved through route model binding (D-014).
 *
 * A price list is commercially sensitive in a way a pet record is not — a competitor learning what
 * a salon charges is a real harm — so every one of these is 404 rather than 403, and the add-on and
 * category paths are checked as well as the bound routes.
 */
final class ServiceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Service $strangersService;

    private Service $strangersAddOn;

    private ServiceCategory $strangersCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $otherTenant = Tenant::factory()->create();

        [$this->strangersService, $this->strangersAddOn, $this->strangersCategory] =
            app(TenantContext::class)->runFor($otherTenant, fn (): array => [
                Service::factory()->named('Their Secret Groom')->priced(199)->create(),
                Service::factory()->addOn()->named('Their Add-On')->create(),
                ServiceCategory::factory()->named('Their Category')->create(),
            ]);

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_another_businesss_service_cannot_be_read(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/services/{$this->strangersService->getKey()}")
            ->assertNotFound();
    }

    public function test_another_businesss_service_cannot_be_repriced(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$this->strangersService->getKey()}", ['price' => '1.00'])
            ->assertNotFound();

        $this->assertSame(19900, $this->strangersService->refresh()->price_cents);
    }

    public function test_another_businesss_service_cannot_be_deactivated(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/services/{$this->strangersService->getKey()}")
            ->assertNotFound();

        $this->assertSame('active', $this->strangersService->refresh()->status->value);
    }

    public function test_another_businesss_service_cannot_have_its_availability_set(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$this->strangersService->getKey()}/availability", [
                'windows' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00']],
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('service_availability_windows', 0);
    }

    public function test_another_businesss_category_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/service-categories/{$this->strangersCategory->getKey()}")
            ->assertNotFound();

        $this->assertDatabaseHas('service_categories', ['id' => $this->strangersCategory->getKey()]);
    }

    /**
     * The category id arrives in a request body, so the tenant-scoped validation rule is the only
     * thing stopping a service being filed under a stranger's category. Refused as "could not be
     * found", which reveals nothing about whether that id exists elsewhere.
     */
    public function test_a_service_cannot_be_filed_under_another_businesss_category(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
                'service_category_id' => $this->strangersCategory->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_category_id');

        $this->assertDatabaseMissing('services', ['name' => 'Full Groom']);
    }

    public function test_another_businesss_add_on_cannot_be_attached(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
                'add_on_ids' => [$this->strangersAddOn->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('add_on_ids');
    }

    public function test_the_service_list_shows_only_this_businesss_menu(): void
    {
        $mine = Service::factory()->named('My Groom')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey());
    }

    /**
     * Searching a price list is its own leak path: an unscoped search would confirm what a
     * competitor calls its services.
     */
    public function test_search_cannot_find_another_businesss_service(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/services?search=Secret')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_category_list_shows_only_this_businesss_categories(): void
    {
        ServiceCategory::factory()->named('Mine')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/service-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    /**
     * The contract every other module will read the catalogue through. Scheduling and Booking both
     * resolve services by a raw id, which is the obvious place for a tenant check to be forgotten.
     */
    public function test_the_catalog_contract_cannot_resolve_another_businesss_service(): void
    {
        $catalog = app(ServiceCatalog::class);

        $this->assertFalse($catalog->exists($this->strangersService->getKey()));
        $this->assertNull($catalog->find($this->strangersService->getKey()));
        $this->assertFalse($catalog->isSellable($this->strangersService->getKey()));
        $this->assertSame([], $catalog->findMany([$this->strangersService->getKey()]));
        $this->assertSame([], $catalog->addOnIdsFor($this->strangersService->getKey()));
        $this->assertSame([], $catalog->bookableOnline());
    }

    /**
     * Fails closed: an unknown service is not available at any time, so a booking request naming a
     * service this business does not have is refused rather than allowed through for something
     * downstream to notice.
     */
    public function test_availability_fails_closed_for_another_businesss_service(): void
    {
        $this->assertFalse(
            app(ServiceCatalog::class)->isAvailableAt(
                $this->strangersService->getKey(),
                new \DateTimeImmutable('2026-10-05 10:00:00')
            )
        );
    }
}
