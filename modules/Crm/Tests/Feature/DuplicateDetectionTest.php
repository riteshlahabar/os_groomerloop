<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Domain\DuplicateMatch;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\DuplicateDetector;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Duplicate detection (spec §8).
 *
 * The rules are asymmetric on purpose, and these tests are mostly about the things detection
 * must NOT claim. A missed duplicate leaves two tidy records someone merges later; a wrong
 * merge combines two families' pets, notes and appointment history and there is no clean way
 * back. So "two customers called John Smith are not the same person" matters more here than
 * catching every real duplicate.
 */
final class DuplicateDetectionTest extends TestCase
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

    public function test_a_shared_email_address_is_a_confident_match(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);
        Customer::factory()->named('Jane', 'Doe')->create(['email' => 'JANE@example.test']);

        $matches = app(DuplicateDetector::class)->for($jane);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]->confident);
    }

    public function test_the_same_phone_number_typed_differently_still_matches(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => null,
            'phone' => '(512) 555-0134',
        ]);

        Customer::factory()->named('Jane', 'Doe')->create([
            'email' => null,
            'phone' => '+1 512 555 0134',
        ]);

        $matches = app(DuplicateDetector::class)->for($jane);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]->confident);

        // Both signals agree — the name as well as the number — so the reason reported is the
        // more specific of the two rather than "same phone number".
        $this->assertSame(DuplicateMatch::REASON_NAME_AND_PARTIAL_CONTACT, $matches[0]->reason);
    }

    /**
     * One matching contact detail and a name that does not match is the household case, so it
     * is reported by which detail matched and left for a human to judge.
     */
    public function test_a_shared_phone_with_different_names_is_reported_as_a_phone_match(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => null,
            'phone' => '512-555-0134',
        ]);

        Customer::factory()->named('Robert', 'Fletcher')->create([
            'email' => null,
            'phone' => '512-555-0134',
        ]);

        $matches = app(DuplicateDetector::class)->for($jane);

        $this->assertCount(1, $matches);
        $this->assertSame(DuplicateMatch::REASON_PHONE, $matches[0]->reason);
        $this->assertFalse($matches[0]->confident);
    }

    /**
     * The most important test in this file. Two customers called John Smith are completely
     * ordinary, and merging them would be serious data loss.
     */
    public function test_a_shared_name_alone_is_not_a_duplicate(): void
    {
        $one = Customer::factory()->named('John', 'Smith')->create([
            'email' => 'john.a@example.test',
            'phone' => '512-555-0001',
        ]);

        Customer::factory()->named('John', 'Smith')->create([
            'email' => 'john.b@example.test',
            'phone' => '512-555-0002',
        ]);

        $this->assertSame([], app(DuplicateDetector::class)->for($one));
    }

    /**
     * A household sharing one email — a couple, or a parent and child — is surfaced but not
     * asserted, so the UI can offer it without implying it is the same person.
     */
    public function test_a_shared_email_with_different_names_is_offered_but_not_confident(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => 'household@example.test',
            'phone' => '512-555-0001',
        ]);

        Customer::factory()->named('Robert', 'Doe')->create([
            'email' => 'household@example.test',
            'phone' => '512-555-0002',
        ]);

        $matches = app(DuplicateDetector::class)->for($jane);

        $this->assertCount(1, $matches);
        $this->assertFalse($matches[0]->confident);
        $this->assertSame('Same email address', $matches[0]->explain());
    }

    public function test_a_matching_name_plus_one_matching_contact_detail_is_confident(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => 'jane@example.test',
            'phone' => '512-555-0001',
        ]);

        Customer::factory()->named('Jane', 'Doe')->create([
            'email' => 'jane@example.test',
            'phone' => '512-555-9999',
        ]);

        $matches = app(DuplicateDetector::class)->for($jane);

        $this->assertTrue($matches[0]->confident);
        $this->assertSame(DuplicateMatch::REASON_NAME_AND_PARTIAL_CONTACT, $matches[0]->reason);
        $this->assertSame('Same name, and one matching contact detail', $matches[0]->explain());
    }

    /**
     * Accents and punctuation must not defeat the name comparison, or "José" and "Jose" would
     * read as two people.
     */
    public function test_names_compare_across_accents_and_punctuation(): void
    {
        $one = Customer::factory()->named('José', "O'Brien")->create([
            'email' => 'shared@example.test',
            'phone' => '512-555-0001',
        ]);

        Customer::factory()->named('Jose', 'OBrien')->create([
            'email' => 'shared@example.test',
            'phone' => '512-555-0002',
        ]);

        $this->assertTrue(app(DuplicateDetector::class)->for($one)[0]->confident);
    }

    /**
     * A record with neither an email nor a phone cannot be tied to anybody, and guessing from
     * the name is the mistake this whole class exists to avoid.
     */
    public function test_a_customer_with_no_contact_details_matches_nothing(): void
    {
        $nameOnly = Customer::factory()->named('Jane', 'Doe')->create(['email' => null, 'phone' => null]);
        Customer::factory()->named('Jane', 'Doe')->create(['email' => null, 'phone' => null]);

        $this->assertSame([], app(DuplicateDetector::class)->for($nameOnly));
    }

    /**
     * A phone fragment must not match. "0134" appears in a great many numbers.
     */
    public function test_a_phone_fragment_too_short_to_identify_anyone_is_ignored(): void
    {
        $jane = Customer::factory()->named('Jane', 'Doe')->create(['email' => null, 'phone' => '0134']);
        Customer::factory()->named('John', 'Smith')->create(['email' => null, 'phone' => '512-555-0134']);

        $this->assertSame([], app(DuplicateDetector::class)->for($jane));
    }

    public function test_a_customer_is_never_its_own_duplicate(): void
    {
        $jane = Customer::factory()->create(['email' => 'jane@example.test']);

        $this->assertSame([], app(DuplicateDetector::class)->for($jane));
    }

    /**
     * Checked before the record exists, so the warning arrives before the book has two of
     * everybody rather than after someone notices.
     */
    public function test_a_candidate_can_be_checked_before_it_is_created(): void
    {
        Customer::factory()->named('Jane', 'Doe')->create(['email' => 'jane@example.test']);

        $matches = app(DuplicateDetector::class)
            ->forCandidate('JANE@EXAMPLE.TEST', null, 'Jane', 'Doe');

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]->confident);
    }

    // --- Over HTTP --------------------------------------------------------------------------

    public function test_the_endpoint_explains_why_each_match_was_suggested(): void
    {
        // Email and phone both match, which is as close to certain as this gets without a
        // human looking — and the reason reported is the contact detail, not the name.
        $jane = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => 'jane@example.test',
            'phone' => '512-555-0134',
        ]);

        $dupe = Customer::factory()->named('Jane', 'Doe')->create([
            'email' => 'jane@example.test',
            'phone' => '512-555-0134',
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$jane->getKey()}/duplicates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.id', $dupe->getKey())
            ->assertJsonPath('data.0.confident', true)
            // "Same email address" justifies a merge prompt in a way a score never would.
            ->assertJsonPath('data.0.explanation', 'Same email address');
    }

    /**
     * Detection suggests; a human decides. Nothing about looking at duplicates may change
     * anything.
     */
    public function test_looking_at_duplicates_changes_nothing(): void
    {
        $jane = Customer::factory()->create(['email' => 'jane@example.test']);
        $dupe = Customer::factory()->create(['email' => 'jane@example.test']);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/customers/{$jane->getKey()}/duplicates")
            ->assertOk();

        $this->assertDatabaseHas('customers', ['id' => $dupe->getKey(), 'deleted_at' => null]);
        $this->assertDatabaseCount('customers', 2);
    }
}
