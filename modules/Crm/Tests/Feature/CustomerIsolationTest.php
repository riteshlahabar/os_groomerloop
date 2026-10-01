<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Invariant #1 for the CRM, proved through route model binding (D-014).
 *
 * This is the shape of test that matters here, and the reason is worth restating: Phase 1's
 * isolation gate passed while a real cross-tenant leak was live, because its test routes used
 * closures with an explicit findOrFail. SubstituteBindings sits in the `api` group and runs
 * before route middleware, so `{customer}` was being resolved before the tenant was known.
 * Route model binding is a separate code path, and it is the one real controllers use — so
 * every bound parameter in this module is exercised below.
 *
 * Every assertion is 404, never 403. A 403 confirms the record exists, which tells one
 * business that another holds a customer with that id.
 */
final class CustomerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $stranger;

    private CustomerTag $strangersTag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $otherTenant = Tenant::factory()->create();

        [$this->stranger, $this->strangersTag] = app(TenantContext::class)->runFor(
            $otherTenant,
            fn (): array => [
                Customer::factory()->named('Someone', 'Else')->create([
                    'email' => 'someone@other-business.test',
                    'phone' => '512-555-7777',
                ]),
                CustomerTag::factory()->named('Their Tag')->create(),
            ]
        );

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_another_businesss_customer_cannot_be_read(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$this->stranger->getKey()}")
            ->assertNotFound();
    }

    public function test_another_businesss_customer_cannot_be_updated(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$this->stranger->getKey()}", ['city' => 'Austin'])
            ->assertNotFound();

        $this->assertNotSame('Austin', $this->stranger->refresh()->city);
    }

    public function test_another_businesss_customer_cannot_be_archived(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/customers/{$this->stranger->getKey()}")
            ->assertNotFound();

        $this->assertSame('active', $this->stranger->refresh()->status->value);
    }

    /**
     * Invariant #9 crossed with invariant #1: the one endpoint that can silence a customer's
     * opt-out must not reach into another business at all.
     */
    public function test_another_businesss_customer_consent_cannot_be_changed(): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$this->stranger->getKey()}/consent", [
                'marketing' => true,
            ])
            ->assertNotFound();

        $this->assertFalse($this->stranger->refresh()->accepts_marketing);
    }

    public function test_another_businesss_customer_has_no_duplicate_listing(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$this->stranger->getKey()}/duplicates")
            ->assertNotFound();
    }

    public function test_another_businesss_tag_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/customer-tags/{$this->strangersTag->getKey()}")
            ->assertNotFound();

        $this->assertDatabaseHas('customer_tags', ['id' => $this->strangersTag->getKey()]);
    }

    public function test_the_customer_list_shows_only_this_businesss_customers(): void
    {
        $mine = Customer::factory()->named('Jane', 'Doe')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->getKey());
    }

    /**
     * Search is its own leak path — spec §8 searches email and phone, and an unscoped search
     * would confirm another business's contact details to anyone who guessed them.
     */
    public function test_search_cannot_find_another_businesss_customer(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=someone@other-business.test')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customers?search=512-555-7777')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_tag_list_shows_only_this_businesss_tags(): void
    {
        CustomerTag::factory()->named('Mine')->create();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/customer-tags')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    /**
     * The export is the one request that hands over an entire customer book, so it gets its
     * own proof rather than trusting that it shares the index's query.
     */
    public function test_the_export_contains_no_other_businesss_customers(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();

        $csv = $this->actingAs($this->owner)
            ->get('/api/v1/customers-export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Jane', $csv);
        $this->assertStringNotContainsString('Someone', $csv);
        $this->assertStringNotContainsString('other-business.test', $csv);
    }

    /**
     * Duplicate detection reaches across the whole book by design, so it is exactly the kind
     * of query that leaks if it is not scoped.
     */
    public function test_duplicate_detection_never_suggests_another_businesss_customer(): void
    {
        $mine = Customer::factory()->create([
            'email' => 'someone@other-business.test',
            'phone' => '512-555-7777',
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$mine->getKey()}/duplicates")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Invariant #1 for the module's own contract, not only its HTTP surface: Pets, Scheduling
     * and Notifications will all reach customers through CustomerDirectory, and a lookup by
     * raw id is the obvious place for a tenant check to be forgotten.
     */
    public function test_the_customer_directory_cannot_resolve_another_businesss_customer(): void
    {
        $directory = app(CustomerDirectory::class);

        $this->assertFalse($directory->exists($this->stranger->getKey()));
        $this->assertNull($directory->nameOf($this->stranger->getKey()));

        // Fails closed: an unknown customer may not be contacted, which is the safe direction
        // for invariant #9 to break in.
        $this->assertFalse($directory->mayContact(
            $this->stranger->getKey(),
            CommunicationChannel::Email
        ));
    }
}
