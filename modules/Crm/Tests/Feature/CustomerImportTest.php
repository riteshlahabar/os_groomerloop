<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Actions\ImportCustomers;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Bringing an existing customer book across (spec §7 step 8, §8).
 *
 * Partial success is the design. A 400-row import where row 112 has a bad email must not throw
 * away the other 399 — a groomer switching systems would simply give up, and that switch is the
 * moment the product is won or lost.
 */
final class CustomerImportTest extends TestCase
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

    public function test_a_book_of_customers_can_be_imported(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [
                    ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test'],
                    ['first_name' => 'John', 'last_name' => 'Smith', 'phone' => '512-555-0134'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseCount('customers', 2);
    }

    /**
     * The whole point of the per-row report.
     */
    public function test_one_bad_row_does_not_lose_the_good_ones(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [
                    ['first_name' => 'Jane'],
                    ['last_name' => 'NoFirstName'],
                    ['first_name' => 'John', 'email' => 'not-an-email'],
                    ['first_name' => 'Mary'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.failed', 2);

        // Reported per row, so the groomer knows which three of four hundred to fix rather
        // than being told the import failed.
        $this->assertSame(1, $response->json('data.results.1.row'));
        $this->assertArrayHasKey('first_name', $response->json('data.results.1.errors'));
        $this->assertArrayHasKey('email', $response->json('data.results.2.errors'));

        $this->assertDatabaseCount('customers', 2);
    }

    /**
     * The single most common way a migration goes wrong: importing into a book that already
     * holds some of the same people, and silently doubling it.
     */
    public function test_a_confident_duplicate_is_skipped_rather_than_doubling_the_book(): void
    {
        $existing = Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [
                    ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'JANE@example.test'],
                    ['first_name' => 'New', 'last_name' => 'Person', 'email' => 'new@example.test'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.results.0.reason', 'duplicate')
            ->assertJsonPath('data.results.0.existing_customer_id', $existing->getKey());

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_duplicate_skipping_can_be_turned_off_deliberately(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'skip_duplicates' => false,
                'rows' => [
                    ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.test'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            // Still counted, so the business can review the near matches afterwards instead
            // of being told everything was fine.
            ->assertJsonPath('data.results.0.possible_duplicates', 1);

        $this->assertDatabaseCount('customers', 2);
    }

    /**
     * §17 measures growth by channel. A migrated book crediting itself to marketing would
     * flatter every acquisition number the product reports.
     */
    public function test_imported_customers_are_attributed_to_the_import_not_to_a_channel(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [['first_name' => 'Jane', 'source' => CustomerSource::GoogleBusiness->value]],
            ])
            ->assertOk();

        $customer = Customer::query()->firstOrFail();

        $this->assertSame(CustomerSource::Import, $customer->source);

        // Imported people have been customers somewhere; they are not fresh leads.
        $this->assertSame(CustomerStatus::Active, $customer->status);
    }

    public function test_tags_in_a_row_are_created_and_applied(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [
                    ['first_name' => 'Jane', 'tags' => ['Nervous', 'Senior']],
                    ['first_name' => 'John', 'tags' => ['nervous']],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 2);

        // "Nervous" and "nervous" are one tag.
        $this->assertDatabaseCount('customer_tags', 2);
        $this->assertDatabaseCount('customer_tag', 3);
    }

    public function test_contact_details_are_normalised_on_import_so_later_matching_works(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [['first_name' => 'Jane', 'email' => ' Jane@Example.TEST ', 'phone' => '(512) 555-0134']],
            ])
            ->assertOk();

        $customer = Customer::query()->firstOrFail();

        $this->assertSame('jane@example.test', $customer->email_normalised);
        $this->assertSame('5125550134', $customer->phone_normalised);
    }

    /**
     * Bounded so one request cannot hold a transaction open over tens of thousands of rows.
     */
    public function test_an_oversized_batch_is_refused(): void
    {
        $rows = array_fill(0, ImportCustomers::MAX_ROWS + 1, ['first_name' => 'Jane']);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', ['rows' => $rows])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_an_empty_import_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', ['rows' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows');
    }

    /**
     * Invariant #8. Moving an entire customer book in is exactly the kind of action a business
     * would later need accounted for.
     */
    public function test_an_import_is_audited_with_its_outcome(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [
                    ['first_name' => 'Jane'],
                    ['last_name' => 'NoFirstName'],
                ],
            ])
            ->assertOk();

        $event = AuditEvent::query()->where('event', 'customer.imported')->firstOrFail();

        $this->assertSame(2, $event->properties['rows']);
        $this->assertSame(1, $event->properties['imported']);
        $this->assertSame(1, $event->properties['failed']);
        $this->assertSame($this->owner->getKey(), $event->user_id);
    }

    /**
     * Invariant #1. An import is a bulk write, so it is worth proving that the rows land in the
     * importing business and nowhere else.
     */
    public function test_imported_customers_belong_to_the_importing_business(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [['first_name' => 'Jane', 'tenant_id' => $otherTenant->getKey()]],
            ])
            ->assertOk()
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('customers', [
            'first_name' => 'Jane',
            'tenant_id' => $this->tenant->getKey(),
        ]);
    }

    /**
     * Consent is not importable. A spreadsheet column saying "sms: yes" is not consent, and
     * §13's SMS channel is the one with direct statutory exposure (invariant #9).
     */
    public function test_an_import_cannot_opt_a_whole_book_into_text_messages(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers/import', [
                'rows' => [[
                    'first_name' => 'Jane',
                    'accepts_sms' => true,
                    'accepts_marketing' => true,
                ]],
            ])
            ->assertOk();

        $customer = Customer::query()->firstOrFail();

        $this->assertFalse($customer->accepts_sms);
        $this->assertFalse($customer->accepts_marketing);
    }
}
