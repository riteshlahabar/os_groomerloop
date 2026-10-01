<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Catalog\Domain\ServiceStatus;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Models\ServiceCategory;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The service menu over HTTP (spec §10).
 */
final class ServiceEndpointTest extends TestCase
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

    public function test_a_service_can_be_created(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'description' => 'Wash, dry, brush out, nails and ears.',
                'price' => '65.00',
                'duration_minutes' => 60,
                'buffer_minutes' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Full Groom')
            ->assertJsonPath('data.price', '65.00')
            ->assertJsonPath('data.price_cents', 6500)
            // What §11 must reserve, as opposed to what §12 shows the customer.
            ->assertJsonPath('data.occupies_minutes', 75)
            ->assertJsonPath('data.status', ServiceStatus::Active->value);

        $this->assertDatabaseHas('services', [
            'name' => 'Full Groom',
            'price_cents' => 6500,
            'tenant_id' => $this->tenant->getKey(),
        ]);
    }

    /**
     * The API speaks dollars because that is what a salon types on a price list; the database keeps
     * cents because money that drifts by a cent per appointment becomes a reconciliation problem
     * nobody can unpick later.
     */
    public function test_a_price_with_awkward_cents_survives_the_round_trip(): void
    {
        // (int) (49.95 * 100) is 4994 in binary floating point. Rounding, not truncation.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Bath & Brush',
                'price' => '49.95',
                'duration_minutes' => 45,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price_cents', 4995)
            ->assertJsonPath('data.price', '49.95');
    }

    public function test_a_name_price_and_duration_are_required(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'duration_minutes']);
    }

    /**
     * A service longer than a working day is a data-entry slip, and once §11 lays it on a calendar
     * it would silently block a groomer's whole week.
     */
    public function test_an_implausible_duration_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 6000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_minutes');
    }

    public function test_a_service_can_be_categorised(): void
    {
        $category = ServiceCategory::factory()->named('Grooming')->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
                'service_category_id' => $category->getKey(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Grooming');
    }

    public function test_add_ons_can_be_attached_to_a_service(): void
    {
        $nails = Service::factory()->addOn()->named('Nail Trim')->create();
        $teeth = Service::factory()->addOn()->named('Teeth Brushing')->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Full Groom',
                'price' => '65.00',
                'duration_minutes' => 60,
                'add_on_ids' => [$nails->getKey(), $teeth->getKey()],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.add_ons');
    }

    /**
     * A full groom cannot be offered as an extra on another full groom.
     */
    public function test_a_service_that_is_not_an_add_on_cannot_be_attached_as_one(): void
    {
        $groom = Service::factory()->named('Full Groom')->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Puppy Groom',
                'price' => '45.00',
                'duration_minutes' => 45,
                'add_on_ids' => [$groom->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('add_on_ids');
    }

    public function test_a_service_cannot_be_its_own_add_on(): void
    {
        $service = Service::factory()->addOn()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}", [
                'add_on_ids' => [$service->getKey()],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('add_on_ids');
    }

    /**
     * An add-on is the leaf of the menu. A chain would make the §12 booking page's total duration a
     * recursive question.
     */
    public function test_an_add_on_cannot_itself_carry_add_ons(): void
    {
        $nails = Service::factory()->addOn()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/services', [
                'name' => 'Ear Clean',
                'price' => '10.00',
                'duration_minutes' => 10,
                'is_add_on' => true,
                'add_on_ids' => [$nails->getKey()],
            ])
            ->assertCreated()
            ->assertJsonCount(0, 'data.add_ons');
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $service = Service::factory()->named('Full Groom')->priced(65)->lasting(60, 15)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}", ['price' => '70.00'])
            ->assertOk()
            ->assertJsonPath('data.price_cents', 7000)
            ->assertJsonPath('data.name', 'Full Groom')
            ->assertJsonPath('data.duration_minutes', 60);
    }

    /**
     * Flipping a service into an add-on after it has been sold would change what every past
     * appointment meant.
     */
    public function test_a_service_cannot_be_turned_into_an_add_on(): void
    {
        $service = Service::factory()->create(['is_add_on' => false]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}", ['is_add_on' => true])
            ->assertOk();

        $this->assertFalse($service->refresh()->is_add_on);
    }

    /**
     * Invariant #4: every appointment ever booked references a service, so §11 history has to keep
     * resolving its name and duration.
     */
    public function test_deleting_a_service_deactivates_it_rather_than_removing_it(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/services/{$service->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseHas('services', [
            'id' => $service->getKey(),
            'status' => ServiceStatus::Inactive->value,
        ]);
    }

    /**
     * §10 lists booking visibility separately from status, because a salon sells plenty over the
     * counter that it does not publish.
     */
    public function test_a_service_can_be_active_in_the_salon_but_not_offered_online(): void
    {
        $service = Service::factory()->notBookableOnline()->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/services/{$service->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.status', ServiceStatus::Active->value)
            ->assertJsonPath('data.is_bookable_online', false)
            ->assertJsonPath('data.is_publicly_bookable', false);
    }

    public function test_an_add_on_is_never_independently_bookable_online(): void
    {
        // It is chosen alongside a service, not instead of one.
        $addOn = Service::factory()->addOn()->create(['is_bookable_online' => true]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/services/{$addOn->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.is_publicly_bookable', false);
    }

    /**
     * The one edit a business may need to reconstruct later: "we charged her last month's price
     * because nobody knew it had gone up".
     */
    public function test_a_price_change_is_audited_with_both_values(): void
    {
        $service = Service::factory()->priced(65)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}", ['price' => '72.50'])
            ->assertOk();

        $event = AuditEvent::query()->where('event', 'service.price_changed')->firstOrFail();

        $this->assertSame(6500, $event->properties['from_cents']);
        $this->assertSame(7250, $event->properties['to_cents']);
        $this->assertSame($this->owner->getKey(), $event->user_id);
    }

    public function test_changing_something_other_than_the_price_writes_no_price_event(): void
    {
        $service = Service::factory()->priced(65)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}", ['name' => 'Deluxe Groom'])
            ->assertOk();

        $this->assertDatabaseMissing('audit_events', ['event' => 'service.price_changed']);
        $this->assertDatabaseHas('audit_events', ['event' => 'service.updated']);
    }

    public function test_creating_and_deactivating_are_audited(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/services', [
            'name' => 'Full Groom',
            'price' => '65.00',
            'duration_minutes' => 60,
        ]);

        $service = Service::query()->firstOrFail();

        $this->actingAs($this->owner)->deleteJson("/api/v1/services/{$service->getKey()}");

        $events = AuditEvent::query()->pluck('event')->all();

        $this->assertContains('service.created', $events);
        $this->assertContains('service.deactivated', $events);
    }

    public function test_the_endpoints_require_authentication(): void
    {
        $service = Service::factory()->create();

        $this->getJson('/api/v1/services')->assertUnauthorized();
        $this->getJson("/api/v1/services/{$service->getKey()}")->assertUnauthorized();
        $this->postJson('/api/v1/services', ['name' => 'X'])->assertUnauthorized();
    }
}
