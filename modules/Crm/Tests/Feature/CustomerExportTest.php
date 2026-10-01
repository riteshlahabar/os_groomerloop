<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\CustomerTag;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Downloading the customer book as CSV (spec §8 "import/export with permissions").
 *
 * The one endpoint in the product that hands an entire customer list to a single request, so
 * the tests care about three things: that it matches what the screen was showing, that it
 * cannot misreport consent, and that it is always audited.
 */
final class CustomerExportTest extends TestCase
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

    public function test_the_export_is_a_csv_download_with_a_header_row(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);

        $response = $this->actingAs($this->owner)->get('/api/v1/customers-export')->assertOk();

        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('first_name,last_name,email', $csv);
        $this->assertStringContainsString('Jane,Doe,jane@example.test', $csv);
    }

    /**
     * Excel opens a UTF-8 CSV as Windows-1252 without a byte-order mark, so an accented
     * customer name arrives mangled in the one tool most salons will open this with.
     */
    public function test_the_file_opens_correctly_in_excel(): void
    {
        Customer::factory()->named('José', 'Álvarez')->create();

        $csv = $this->actingAs($this->owner)->get('/api/v1/customers-export')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('José', $csv);
    }

    /**
     * A filtered list that exported the whole book is a data-protection incident nobody would
     * notice until it had happened a few times.
     */
    public function test_the_export_matches_the_filters_the_screen_was_showing(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();
        Customer::factory()->named('John', 'Smith')->create();

        $csv = $this->actingAs($this->owner)
            ->get('/api/v1/customers-export?search=Jane')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Jane', $csv);
        $this->assertStringNotContainsString('Smith', $csv);
    }

    public function test_archived_customers_are_excluded_unless_asked_for(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();
        Customer::factory()->named('Gone', 'Away')->archived()->create();

        $csv = $this->actingAs($this->owner)->get('/api/v1/customers-export')->streamedContent();
        $this->assertStringNotContainsString('Gone', $csv);

        $withArchived = $this->actingAs($this->owner)
            ->get('/api/v1/customers-export?include_archived=1')
            ->streamedContent();

        $this->assertStringContainsString('Gone', $withArchived);
    }

    /**
     * Invariant #9. A spreadsheet showing accepts_email = 1 for someone who has opted out is
     * precisely how a business ends up mailing them from a mail-merge.
     */
    public function test_an_opted_out_customer_reads_as_contactable_on_no_channel(): void
    {
        Customer::factory()->named('Quiet', 'One')->fullyConsented()->optedOut()->create();

        $csv = $this->actingAs($this->owner)
            ->get('/api/v1/customers-export')
            ->streamedContent();

        $row = collect(explode("\n", $csv))->first(fn (string $line) => str_contains($line, 'Quiet'));

        // The four consent columns plus the opted_out column, all resolved rather than raw.
        $this->assertStringContainsString('no,no,no,no,yes', (string) $row);
    }

    public function test_tags_are_included_so_the_export_is_usable_as_a_working_list(): void
    {
        $tag = CustomerTag::factory()->named('Nervous')->create();
        $customer = Customer::factory()->named('Jane', 'Doe')->create();
        $customer->tags()->attach($tag, ['tenant_id' => $this->tenant->getKey()]);

        $csv = $this->actingAs($this->owner)->get('/api/v1/customers-export')->streamedContent();

        $this->assertStringContainsString('Nervous', $csv);
    }

    /**
     * Invariant #8. Who exported what, and when, is exactly what an audit trail is for.
     */
    public function test_every_export_is_audited(): void
    {
        Customer::factory()->count(3)->create();

        $this->actingAs($this->owner)->get('/api/v1/customers-export')->assertOk();

        $event = AuditEvent::query()->where('event', 'customer.exported')->firstOrFail();

        $this->assertSame(3, $event->properties['matched']);
        $this->assertSame($this->owner->getKey(), $event->user_id);
    }

    /**
     * The audit has to record the *filtered* count. An entry claiming four thousand customers
     * left the building when the request asked for one tag is worse than no number at all,
     * because it would be believed.
     */
    public function test_the_audit_records_how_many_customers_the_filters_matched(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create();
        Customer::factory()->count(4)->create();

        $this->actingAs($this->owner)->get('/api/v1/customers-export?search=Jane')->assertOk();

        $event = AuditEvent::query()->where('event', 'customer.exported')->firstOrFail();

        $this->assertSame(1, $event->properties['matched']);
        $this->assertSame('Jane', $event->properties['filters']['search']);
    }

    /**
     * Chunked and streamed: a business with twenty thousand customers should not need twenty
     * thousand models resident to download them. Proved by paging past the chunk size, which
     * is also where a mis-ordered cursor would start dropping rows out of the middle.
     */
    public function test_every_customer_appears_exactly_once_across_chunk_boundaries(): void
    {
        Customer::factory()->count(600)->named('John', 'Smith')->create();

        $csv = $this->actingAs($this->owner)->get('/api/v1/customers-export')->streamedContent();

        $rows = array_filter(explode("\n", trim($csv)), static fn (string $l): bool => str_contains($l, 'John'));

        $this->assertCount(600, $rows);
    }
}
