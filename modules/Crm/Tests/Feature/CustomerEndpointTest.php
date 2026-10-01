<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The customer book over HTTP (spec §8).
 */
final class CustomerEndpointTest extends TestCase
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

    public function test_a_customer_can_be_created_with_only_a_first_name(): void
    {
        // Spec §8: a salon takes a name over the counter and fills the rest in later.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane'])
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.status', CustomerStatus::Active->value);

        $this->assertDatabaseHas('customers', [
            'first_name' => 'Jane',
            'tenant_id' => $this->tenant->getKey(),
        ]);
    }

    /**
     * A customer typed in by hand has been spoken to. Defaulting to Lead would count every
     * walk-in against the §36 conversion rate.
     */
    public function test_a_customer_added_by_hand_is_active_not_a_lead(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane'])
            ->assertCreated()
            ->assertJsonPath('data.status', CustomerStatus::Active->value);
    }

    public function test_creating_a_customer_normalises_contact_details_for_matching(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', [
                'first_name' => 'Jane',
                'email' => '  Jane@Example.TEST ',
                'phone' => '+1 (512) 555-0134',
                'country' => 'us',
            ])
            ->assertCreated();

        $customer = Customer::query()->firstOrFail();

        $this->assertSame('jane@example.test', $customer->email_normalised);
        $this->assertSame('5125550134', $customer->phone_normalised);

        // prepareForValidation upper-cases the country so "us" and "US" are one value.
        $this->assertSame('US', $customer->country);
    }

    public function test_tags_are_created_on_the_fly_when_a_customer_is_created(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', [
                'first_name' => 'Jane',
                'tags' => ['Nervous', 'nervous', 'Senior'],
            ])
            ->assertCreated()
            // "Nervous" and "nervous" slug the same, so they are one tag.
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseCount('customer_tags', 2);
    }

    public function test_a_first_name_is_required(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['last_name' => 'Doe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    public function test_an_unparseable_email_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_a_customer_can_be_read_back(): void
    {
        $customer = Customer::factory()->named('Jane', 'Doe')->create();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$customer->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Jane Doe')
            ->assertJsonPath('data.consent.opted_out', false)
            ->assertJsonStructure([
                'data' => ['id', 'first_name', 'status', 'consent' => ['channels']],
            ]);
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $customer = Customer::factory()->create([
            'first_name' => 'Jane',
            'city' => 'Austin',
            'notes' => 'Prefers mornings.',
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Dallas'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Dallas')
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.notes', 'Prefers mornings.');
    }

    public function test_an_explicit_null_clears_a_field(): void
    {
        $customer = Customer::factory()->create(['city' => 'Austin']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => null])
            ->assertOk()
            ->assertJsonPath('data.city', null);
    }

    /**
     * Null means "leave tags alone", [] means "remove them all". Collapsing the two would
     * make it impossible to clear a customer's tags.
     */
    public function test_tags_are_left_alone_when_absent_and_cleared_when_empty(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['tags' => ['Nervous']])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Austin'])
            ->assertOk()
            ->assertJsonCount(1, 'data.tags');

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['tags' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.tags');
    }

    /**
     * Invariant #4. A customer carries pets, appointments and history; tidying the list must
     * not be able to destroy them.
     */
    public function test_deleting_a_customer_archives_it_rather_than_removing_it(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/customers/{$customer->getKey()}")
            ->assertNoContent();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->getKey(),
            'status' => CustomerStatus::Archived->value,
            'deleted_at' => null,
        ]);
    }

    /**
     * Invariant #8: the actions a business would later need to account for are recorded.
     */
    public function test_creating_updating_and_archiving_are_all_audited(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/customers', ['first_name' => 'Jane']);

        $customer = Customer::query()->firstOrFail();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Austin']);

        $this->actingAs($this->owner)->deleteJson("/api/v1/customers/{$customer->getKey()}");

        $events = AuditEvent::query()->pluck('event')->all();

        $this->assertContains('customer.created', $events);
        $this->assertContains('customer.updated', $events);
        $this->assertContains('customer.archived', $events);
    }

    /**
     * An audit trail that records every field on every edit is one nobody reads.
     */
    public function test_an_update_audits_only_the_fields_that_moved(): void
    {
        $customer = Customer::factory()->create(['city' => 'Austin', 'first_name' => 'Jane']);

        $this->actingAs($this->owner)->putJson("/api/v1/customers/{$customer->getKey()}", [
            'first_name' => 'Jane',
            'city' => 'Dallas',
        ]);

        $event = AuditEvent::query()->where('event', 'customer.updated')->firstOrFail();

        $this->assertSame(['city'], $event->properties['changed']);
    }

    public function test_an_update_that_changes_nothing_writes_no_audit_event(): void
    {
        $customer = Customer::factory()->create(['city' => 'Austin']);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", ['city' => 'Austin'])
            ->assertOk();

        $this->assertDatabaseMissing('audit_events', ['event' => 'customer.updated']);
    }

    public function test_source_is_recorded_for_growth_reporting(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', [
                'first_name' => 'Jane',
                'source' => CustomerSource::Referral->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', 'referral')
            ->assertJsonPath('data.source_label', 'Referral');
    }

    public function test_an_unknown_source_is_refused_rather_than_stored_as_free_text(): void
    {
        // §17 measures growth by channel; free text would give one channel five spellings.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane', 'source' => 'tiktok_maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    /**
     * Invariant #9. Consent is not fillable, so the ordinary customer endpoints cannot become
     * a second, unaudited way to change it.
     */
    public function test_consent_cannot_be_set_through_the_ordinary_customer_endpoints(): void
    {
        $customer = Customer::factory()->create(['accepts_marketing' => false]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", [
                'accepts_marketing' => true,
                'accepts_sms' => true,
                'opted_out_at' => null,
                'city' => 'Austin',
            ])
            ->assertOk();

        $customer->refresh();

        $this->assertFalse($customer->accepts_marketing);
        $this->assertFalse($customer->accepts_sms);
        $this->assertSame('Austin', $customer->city);
    }

    public function test_a_customer_cannot_be_moved_to_another_business_by_mass_assignment(): void
    {
        $otherTenant = Tenant::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}", [
                'tenant_id' => $otherTenant->getKey(),
                'city' => 'Austin',
            ])
            ->assertOk();

        $this->assertSame($this->tenant->getKey(), $customer->refresh()->tenant_id);
    }

    public function test_the_endpoints_require_authentication(): void
    {
        $customer = Customer::factory()->create();

        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->getJson("/api/v1/customers/{$customer->getKey()}")->assertUnauthorized();
        $this->postJson('/api/v1/customers', ['first_name' => 'Jane'])->assertUnauthorized();
    }
}
