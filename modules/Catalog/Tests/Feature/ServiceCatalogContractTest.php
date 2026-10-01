<?php

namespace Modules\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Models\ServiceCategory;
use Modules\Catalog\Services\EloquentServiceCatalog;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The seam Scheduling, Booking, Team and Insights will all read the catalogue through (D-007).
 *
 * Tested as a contract in its own right rather than only through the HTTP endpoints, because these
 * are the methods other modules will build on — and none of them will go near a controller. A change
 * that quietly altered what `isSellable` or `allowsAddOn` means would break the booking engine
 * rather than a screen.
 */
final class ServiceCatalogContractTest extends TestCase
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

    public function test_it_summarises_a_service_without_handing_over_the_model(): void
    {
        $category = ServiceCategory::factory()->named('Grooming')->create();

        $service = Service::factory()
            ->named('Full Groom')
            ->priced(65)
            ->lasting(60, 15)
            ->inCategory($category->getKey())
            ->create();

        $summary = $this->catalog()->find($service->getKey());

        $this->assertNotNull($summary);
        $this->assertSame('Full Groom', $summary->name);
        $this->assertSame(6500, $summary->priceCents);
        $this->assertSame('65.00', $summary->price());
        $this->assertSame(60, $summary->durationMinutes);
        $this->assertSame(15, $summary->bufferMinutes);
        $this->assertSame('Grooming', $summary->categoryName);

        // Readonly, and not an Eloquent model: a scheduling bug must not be able to save the price
        // list through the object it was handed.
        $this->assertFalse(method_exists($summary, 'save'));
    }

    /**
     * The sum is precomputed because it is the whole point of having two columns. A caller that
     * forgot the buffer would book grooms back to back with no time to clean down.
     */
    public function test_the_summary_carries_the_time_the_calendar_must_reserve(): void
    {
        $service = Service::factory()->lasting(90, 20)->create();

        $summary = $this->catalog()->find($service->getKey());

        $this->assertSame(110, $summary?->occupiesMinutes);
    }

    public function test_it_resolves_several_services_in_one_call(): void
    {
        $groom = Service::factory()->named('Full Groom')->create();
        $nails = Service::factory()->addOn()->named('Nail Trim')->create();

        $summaries = $this->catalog()->findMany([$groom->getKey(), $nails->getKey(), 9_999_999]);

        // An appointment carries a service plus its add-ons, so they come back keyed by id — and an
        // id that resolves to nothing is simply absent rather than a null the caller must check.
        $this->assertCount(2, $summaries);
        $this->assertSame('Full Groom', $summaries[$groom->getKey()]->name);
        $this->assertSame('Nail Trim', $summaries[$nails->getKey()]->name);
        $this->assertArrayNotHasKey(9_999_999, $summaries);
    }

    /**
     * Fails closed: a stale picker in an open browser tab must not be able to book something the
     * business has retired.
     */
    public function test_a_retired_service_is_not_sellable(): void
    {
        $active = Service::factory()->create();
        $retired = Service::factory()->inactive()->create();

        $catalog = $this->catalog();

        $this->assertTrue($catalog->isSellable($active->getKey()));
        $this->assertFalse($catalog->isSellable($retired->getKey()));
        $this->assertFalse($catalog->isSellable(9_999_999));
    }

    /**
     * What the §12 public booking page may offer, decided server-side. Three conditions, and all
     * three have to hold.
     */
    public function test_it_lists_only_what_the_public_may_book(): void
    {
        Service::factory()->named('Full Groom')->create();
        Service::factory()->named('Hand Strip')->notBookableOnline()->create();
        Service::factory()->addOn()->named('Nail Trim')->create();
        Service::factory()->named('Retired')->inactive()->create();

        $bookable = $this->catalog()->bookableOnline();

        $this->assertCount(1, $bookable);
        $this->assertSame('Full Groom', $bookable[0]->name);
    }

    /**
     * The check a booking request needs: a de-shed treatment must not be attachable to a nail trim it
     * was never offered with. Both ids come from the client.
     */
    public function test_it_refuses_an_add_on_the_service_does_not_offer(): void
    {
        $groom = Service::factory()->named('Full Groom')->create();
        $offered = Service::factory()->addOn()->named('Nail Trim')->create();
        $notOffered = Service::factory()->addOn()->named('De-shed')->create();

        $groom->addOns()->attach($offered, ['tenant_id' => $this->tenant->getKey()]);

        $catalog = $this->catalog();

        $this->assertTrue($catalog->allowsAddOn($groom->getKey(), $offered->getKey()));
        $this->assertFalse($catalog->allowsAddOn($groom->getKey(), $notOffered->getKey()));

        $this->assertSame([$offered->getKey()], $catalog->addOnIdsFor($groom->getKey()));
    }

    /**
     * A retired add-on stays attached for history but must not be offered on a new appointment.
     */
    public function test_a_retired_add_on_is_no_longer_offered(): void
    {
        $groom = Service::factory()->named('Full Groom')->create();
        $addOn = Service::factory()->addOn()->create();

        $groom->addOns()->attach($addOn, ['tenant_id' => $this->tenant->getKey()]);

        $this->assertSame([$addOn->getKey()], $this->catalog()->addOnIdsFor($groom->getKey()));

        $addOn->update(['status' => 'inactive']);

        $this->assertSame([], $this->catalog()->addOnIdsFor($groom->getKey()));
        $this->assertFalse($this->catalog()->allowsAddOn($groom->getKey(), $addOn->getKey()));

        // Still attached, so an appointment booked last month still resolves it.
        $this->assertDatabaseHas('service_add_on', [
            'service_id' => $groom->getKey(),
            'add_on_service_id' => $addOn->getKey(),
        ]);
    }

    /**
     * Used by the §7 checklist, and "any" means any *sellable* one: a business whose only service is
     * retired cannot take a booking.
     */
    public function test_has_any_ignores_retired_services(): void
    {
        $this->assertFalse($this->catalog()->hasAny());

        $service = Service::factory()->inactive()->create();
        $this->assertFalse($this->catalog()->hasAny());

        $service->update(['status' => 'active']);
        $this->assertTrue($this->catalog()->hasAny());
    }

    /**
     * A fresh instance each time: the catalogue memoises per request, and the container's singleton
     * outlives a request in tests — so one resolved before a status change would answer from a stale
     * model. The same lesson the entitlement service taught in Phase 3.
     */
    private function catalog(): ServiceCatalog
    {
        return new EloquentServiceCatalog;
    }
}
